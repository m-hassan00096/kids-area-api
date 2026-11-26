<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\Identity\Models\User;
use Modules\Identity\Services\Avatar\AvatarFormat;
use Modules\Identity\Services\Avatar\AvatarImageNormalizer;
use Modules\Identity\Services\Avatar\AvatarLifecycle;
use Modules\Identity\Services\Avatar\AvatarStorage;
use Modules\Identity\Services\Avatar\NormalizedAvatar;
use Modules\Identity\Services\Avatar\ReadUserAvatar;
use RuntimeException;
use Tests\Support\AvatarFixtures;

class AvatarInfrastructureTest extends IdentityDatabaseTestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir().'/kids-avatar-'.bin2hex(random_bytes(10));
        $this->assertTrue(mkdir($this->root, 0700));
        config()->set('filesystems.disks.identity-avatars.root', $this->root);
        Storage::forgetDisk('identity-avatars');
    }

    protected function tearDown(): void
    {
        if (isset($this->root) && is_dir($this->root)) {
            chmod($this->root, 0700);
            foreach (glob($this->root.'/*') ?: [] as $path) {
                unlink($path);
            }
            rmdir($this->root);
        }
        parent::tearDown();
    }

    public function test_native_normalization_preserves_format_dimensions_and_transparency(): void
    {
        $normalizer = app(AvatarImageNormalizer::class);
        foreach (['png', 'jpg', 'webp'] as $extension) {
            $source = AvatarFixtures::image($extension, 2048, 1024);
            $normalized = $normalizer->normalize($source, $extension);
            $this->assertSame($extension, $normalized->extension);
            $this->assertTrue(AvatarFormat::structure($normalized->bytes, $extension));
            $this->assertLessThanOrEqual(AvatarFormat::LIMIT, strlen($normalized->bytes));
            $decoded = imagecreatefromstring($normalized->bytes);
            $this->assertSame([512, 256], [imagesx($decoded), imagesy($decoded)]);
            if ($extension !== 'jpg') {
                $this->assertSame(40, (imagecolorat($decoded, 511, 255) >> 24) & 127);
            }
        }
        $progressive = AvatarFixtures::image('jpg', 64, 32, true);
        $this->assertStringContainsString("\xff\xc2", $progressive);
        $restart = AvatarFixtures::restartJpeg();
        $this->assertStringContainsString("\xff\xdd", $restart);
        $this->assertStringContainsString("\xff\xd0", $restart);
        $this->assertStringContainsString("\xff\x00", $restart);
        $appMarker = substr($progressive, 0, 2)."\xff\xe1".pack('n', 8)."x\xff\xd9abc".substr($progressive, 2);
        foreach ([$progressive, $restart, $appMarker] as $jpeg) {
            $this->assertTrue(AvatarFormat::structure($jpeg, 'jpg'));
            $this->assertSame('jpg', $normalizer->normalize($jpeg, 'jpg')->extension);
        }
        $exact = AvatarFixtures::pngOfSize(AvatarFormat::LIMIT);
        $this->assertSame(AvatarFormat::LIMIT, strlen($exact));
        $output = $normalizer->normalize($exact, 'png');
        $this->assertSame([8, 8], array_slice(getimagesizefromstring($output->bytes), 0, 2));
        $this->assertStringNotContainsString('SYNTHETIC-AVATAR-METADATA', $output->bytes);
    }

    public function test_malformed_animated_trailing_spoofed_and_over_limit_inputs_are_rejected(): void
    {
        $normalizer = app(AvatarImageNormalizer::class);
        $png = AvatarFixtures::image('png');
        $jpg = AvatarFixtures::image('jpg');
        $webp = AvatarFixtures::image('webp');
        $restart = AvatarFixtures::restartJpeg();
        $animation = 'VP8X'.pack('V', 10)."\x02".str_repeat("\x00", 9);
        $riff = 'WEBP'.$animation.substr($webp, 12);
        foreach ([
            ['empty', '', 'png'],
            ['spoof', $png, 'jpg'],
            ['svg', '<svg></svg>', 'png'],
            ['gif', 'GIF89a', 'jpg'],
            ['jpeg-dual', $jpg.$png."\xff\xd9", 'jpg'],
            ['jpeg-concatenated', $jpg.$jpg, 'jpg'],
            ['jpeg-length', substr($jpg, 0, 4)."\x00\x01".substr($jpg, 6), 'jpg'],
            ['jpeg-truncated', substr($jpg, 0, -2), 'jpg'],
            ['jpeg-restart-order', str_replace("\xff\xd0", "\xff\xd3", $restart), 'jpg'],
            ['png-crc', substr($png, 0, 29).chr(ord($png[29]) ^ 1).substr($png, 30), 'png'],
            ['png-duplicate-ihdr', substr($png, 0, 33).substr($png, 8, 25).substr($png, 33), 'png'],
            ['png-animation', substr($png, 0, 33).AvatarFixtures::chunk('acTL', pack('NN', 2, 0)).substr($png, 33), 'png'],
            ['png-trailing', $png.$jpg, 'png'],
            ['webp-riff', substr($webp, 0, 4).pack('V', 0).substr($webp, 8), 'webp'],
            ['webp-animation', 'RIFF'.pack('V', strlen($riff)).$riff, 'webp'],
            ['webp-trailing', $webp.$png, 'webp'],
            ['dimensions', AvatarFixtures::image('png', 2049, 1), 'png'],
            ['size', AvatarFixtures::pngOfSize(AvatarFormat::LIMIT + 1), 'png'],
        ] as [$case, $bytes, $extension]) {
            try {
                $normalizer->normalize($bytes, $extension);
                $this->fail('Invalid synthetic fixture accepted: '.$case);
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('avatar', $exception->errors(), $case);
            }
        }
    }

    public function test_private_exclusive_storage_does_not_overwrite_or_follow_symlinks(): void
    {
        $image = app(AvatarImageNormalizer::class)->normalize(AvatarFixtures::image('png'), 'png');
        $storage = app(AvatarStorage::class);
        $key = $storage->stage($image);
        $this->assertTrue(AvatarFormat::validKey($key));
        $this->assertSame(0600, fileperms($this->root.'/'.$key) & 0777);
        $this->assertSame($image->bytes, $storage->read($key)->bytes);
        $this->assertFalse(config('filesystems.disks.identity-avatars.serve'));
        $this->assertFalse(config('filesystems.disks.local.serve'));
        $collision = new class($key) extends AvatarStorage
        {
            public function __construct(private string $key) {}

            protected function newKey(string $extension): string
            {
                return $this->key;
            }
        };
        try {
            $collision->stage(app(AvatarImageNormalizer::class)->normalize(AvatarFixtures::image('png', 9, 9), 'png'));
            $this->fail('Existing file overwritten.');
        } catch (RuntimeException) {
            $this->assertSame($image->bytes, $storage->read($key)->bytes);
        }
        try {
            $storage->stage(new NormalizedAvatar('png', AvatarFixtures::image('png', 513, 1)));
            $this->fail('Non-normalized dimensions accepted.');
        } catch (RuntimeException) {
            $this->assertSame(1, count(glob($this->root.'/*')));
        }
        $link = str_repeat('a', 32).'.png';
        $this->assertTrue(symlink($this->root.'/'.$key, $this->root.'/'.$link));
        foreach (['../'.$key, $link, str_repeat('b', 32).'.svg'] as $bad) {
            try {
                $storage->read($bad);
                $this->fail('Unsafe read succeeded.');
            } catch (RuntimeException) {
                $this->assertSame($image->bytes, $storage->read($key)->bytes);
            }
        }
        chmod($this->root, 0755);
        try {
            $storage->stage($image);
            $this->fail('Nonprivate root accepted.');
        } catch (RuntimeException) {
            $this->assertSame(2, count(glob($this->root.'/*')));
        } finally {
            chmod($this->root, 0700);
        }
    }

    public function test_cleanup_obeys_actual_reference_and_transaction_outcome(): void
    {
        $lifecycle = app(AvatarLifecycle::class);
        $storage = app(AvatarStorage::class);
        $image = app(AvatarImageNormalizer::class)->normalize(AvatarFixtures::image('png'), 'png');
        $user = User::factory()->create();
        $other = User::factory()->create();
        $old = $lifecycle->stage($image);
        $candidate = $lifecycle->stage($image);
        $otherKey = $lifecycle->stage($image);
        DB::table('users')->where('id', $user->id)->update(['avatar_key' => $old]);
        DB::table('users')->where('id', $other->id)->update(['avatar_key' => $otherKey]);
        DB::beginTransaction();
        DB::table('users')->where('id', $user->id)->update(['avatar_key' => $candidate]);
        DB::rollBack();
        $lifecycle->finish($candidate, $old, 'rolled_back');
        $this->assertFileDoesNotExist($this->root.'/'.$candidate);
        $this->assertSame($image->bytes, $storage->read($old)->bytes);

        $candidate = $lifecycle->stage($image);
        $lifecycle->finish($candidate, $old, 'unknown');
        $this->assertFileExists($this->root.'/'.$candidate);
        $this->assertFileExists($this->root.'/'.$old);
        DB::transaction(fn () => DB::table('users')->where('id', $user->id)->update(['avatar_key' => $candidate]));
        $lifecycle->finish($candidate, $old, 'committed');
        $this->assertFileDoesNotExist($this->root.'/'.$old);
        $this->assertFileExists($this->root.'/'.$candidate);
        $lifecycle->finish($candidate, $otherKey, 'committed');
        $lifecycle->finish($candidate, null, 'rolled_back');
        $this->assertFileExists($this->root.'/'.$otherKey);
        $this->assertFileExists($this->root.'/'.$candidate);

        // A cleanup failure must not reinterpret a committed DB reference.
        $failedOld = $lifecycle->stage($image);
        $fault = new class extends AvatarStorage
        {
            public function delete(string $key): void
            {
                throw new RuntimeException('Synthetic cleanup failure.');
            }
        };
        (new AvatarLifecycle($fault))->finish($candidate, $failedOld, 'committed');
        $this->assertFileExists($this->root.'/'.$failedOld);
        $this->assertSame($candidate, $user->fresh()->avatar_key);
    }

    public function test_private_read_rechecks_replaced_key_once(): void
    {
        $normalizer = app(AvatarImageNormalizer::class);
        $storage = app(AvatarStorage::class);
        $user = User::factory()->create();
        $old = $storage->stage($normalizer->normalize(AvatarFixtures::image('png'), 'png'));
        $new = $storage->stage($normalizer->normalize(AvatarFixtures::image('jpg'), 'jpg'));
        DB::table('users')->where('id', $user->id)->update(['avatar_key' => $old]);
        $target = $user->fresh();
        $race = new class($user->id, $old, $new) extends AvatarStorage
        {
            public int $reads = 0;

            public function __construct(private int $userId, private string $old, private string $new) {}

            public function read(string $key): NormalizedAvatar
            {
                $this->reads++;
                if ($key === $this->old) {
                    DB::table('users')->where('id', $this->userId)->update(['avatar_key' => $this->new]);
                    parent::delete($this->old);
                }

                return parent::read($key);
            }
        };
        $this->assertSame('jpg', (new ReadUserAvatar($race))->execute($target)->extension);
        $this->assertSame(2, $race->reads);
    }
}
