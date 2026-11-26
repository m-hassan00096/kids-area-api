<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Identity\Models\Role;
use Modules\Identity\Services\Avatar\AvatarFormat;
use Modules\Identity\Services\Avatar\AvatarImageNormalizer;
use Modules\Identity\Services\Avatar\AvatarStorage;
use Tests\Support\AvatarFixtures;

class AvatarHttpTest extends AuthenticationHttpTestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = $this->barrierDir.'/avatars';
        $this->assertTrue(mkdir($this->root, 0700));
        config()->set('filesystems.disks.identity-avatars.root', $this->root);
        Storage::forgetDisk('identity-avatars');
    }

    public function test_private_binary_read_headers_permissions_and_missing_reference(): void
    {
        $actor = $this->user('Fixture.Avatar');
        $target = $this->user('Fixture.Avatar.Target', 'reception');
        $server = $this->server();
        $browser = $this->browser($server);
        $this->assertSame(200, $browser->login($actor->username)->status);
        $path = '/api/v1/users/'.$target->id.'/avatar';
        $this->assertSame(404, $browser->request('GET', $path)->status);
        $storage = app(AvatarStorage::class);
        foreach (['png', 'jpg', 'webp'] as $extension) {
            $image = app(AvatarImageNormalizer::class)->normalize(AvatarFixtures::image($extension), $extension);
            $key = $storage->stage($image);
            DB::table('users')->where('id', $target->id)->update(['avatar_key' => $key]);
            $response = $browser->request('GET', $path);
            $this->assertSame(200, $response->status, $response->header('X-Test-Exception-Class') ?? '');
            $this->assertSame($image->bytes, $response->body);
            $this->assertSame(AvatarFormat::mime($extension), $response->header('Content-Type'));
            $this->assertSame('inline; filename="avatar.'.$extension.'"', $response->header('Content-Disposition'));
            $this->assertStringContainsString('private', $response->header('Cache-Control'));
            $this->assertStringContainsString('no-store', $response->header('Cache-Control'));
            $this->assertSame('nosniff', $response->header('X-Content-Type-Options'));
            $this->assertTrue(Str::isUuid($response->header('X-Request-ID')));
            $this->assertSame(404, $browser->request('GET', '/storage/identity-avatars/'.$key)->status);
        }
        $this->assertSame(401, $this->browser($server)->request('GET', $path)->status);
        $this->assertSame(403, $browser->request('GET', $path, options: ['headers' => ['Origin' => 'https://unapproved.invalid']])->status);
        $limited = Role::create(['name' => 'fixture-avatar-denied', 'guard_name' => 'web', 'alias_name' => 'Fixture']);
        $staff = $this->user('Fixture.Avatar.Denied', $limited->name);
        $denied = $this->browser($server);
        $this->assertSame(200, $denied->login($staff->username)->status);
        $this->assertSame(403, $denied->request('GET', $path)->status);
        $storage->delete($key);
        $response = $browser->request('GET', $path, options: ['headers' => ['X-Identity-Test-Inspect' => '1']]);
        $this->assertSame(500, $response->status);
        $this->assertSame(['success' => false, 'message' => __('api.errors.internal'), 'errors' => []], $response->json());
        $this->assertSame('1', $response->header('X-Test-Redacted'));
        $this->assertSame('1', $response->header('X-Test-Error-Report-Count'));
        $this->assertStringNotContainsString($key, $response->body);
    }

    public function test_real_native_upload_boundaries_on_guarded_existing_routes(): void
    {
        $actor = $this->user('Fixture.Avatar.Upload');
        $browser = $this->browser($this->server());
        $this->assertSame(200, $browser->login($actor->username)->status);
        $create = '/api/v1/users';
        $update = $create.'/'.$actor->id;
        $png = AvatarFixtures::image('png');
        $jpg = AvatarFixtures::image('jpg');
        $webp = AvatarFixtures::image('webp');
        $exact = AvatarFixtures::pngOfSize(AvatarFormat::LIMIT);
        $oversize = AvatarFixtures::pngOfSize(AvatarFormat::LIMIT + 1);
        foreach ([$create, $update] as $path) {
            $prefix = $path === $update ? [['field', '_method', 'PUT']] : [];
            foreach ([['avatar.png', $png], ['avatar.jpeg', $jpg], ['avatar.webp', $webp], ['avatar.png', $exact]] as [$name, $bytes]) {
                $result = $browser->request('POST', $path, options: $this->multipart([...$prefix, ['file', 'avatar', $name, $bytes]]));
                $this->assertSame(503, $result->status, $result->header('X-Test-Exception-Class') ?? '');
                $this->assertSame('1', $result->header('X-Test-Avatar-Validated'));
            }
            foreach ([
                [['file', 'avatar', 'avatar.png', $oversize]],
                [['file', 'avatar', 'avatar.png', '']],
                [['file', 'avatar', '', '']],
                [['file', 'avatar', 'avatar.jpg', $png]],
                [['file', 'avatar', 'avatar.png', 'not-image']],
                [['file', 'avatar[]', 'avatar.png', $png]],
                [['file', 'avatar', 'avatar.png', $png], ['file', 'extra', 'extra.png', $png]],
                [['file', 'avatar', 'avatar.png', $png], ['file', 'avatar[]', 'avatar.png', $png]],
                [['file', 'avatar', 'avatar.jpg', $jpg.$png."\xff\xd9"]],
            ] as $parts) {
                $result = $browser->request('POST', $path, options: $this->multipart([...$prefix, ...$parts]));
                $this->assertSame(422, $result->status, $result->header('X-Test-Exception-Class') ?? '');
                $this->assertArrayHasKey('avatar', $result->json()['errors']);
                $this->assertNull($result->header('X-Test-Avatar-Validated'));
            }
            // Native duplicate scalar parts collapse; validate the retained one.
            $result = $browser->request('POST', $path, options: $this->multipart([...$prefix, ['file', 'avatar', 'invalid.png', 'invalid'], ['file', 'avatar', 'valid.png', $png]]));
            $this->assertSame(503, $result->status);
            $this->assertSame('1', $result->header('X-Test-Avatar-Validated'));
            $result = $browser->request('POST', $path, options: $this->multipart([...$prefix, ['file', 'avatar', 'valid.png', $png], ['file', 'avatar', 'invalid.png', 'invalid']]));
            $this->assertSame(422, $result->status);
            foreach (['missing', 'bad'] as $csrf) {
                $result = $browser->request('POST', $path, options: $this->multipart([...$prefix, ['file', 'avatar', 'avatar.png', $png]]) + ['csrf' => $csrf]);
                $this->assertSame(419, $result->status);
                $this->assertNull($result->header('X-Test-Avatar-Validated'));
            }
        }
        $result = $browser->request('POST', $update, options: $this->multipart([['field', '_method', 'PUT'], ['field', 'removeAvatar', 'true'], ['file', 'avatar', 'avatar.png', $png]]));
        $this->assertSame(422, $result->status);
        $result = $browser->request('POST', $create, options: $this->multipart([['file', 'avatar', 'large.png', str_repeat('x', 4194304)]]));
        $this->assertSame(413, $result->status);
        $this->assertSame(1, DB::table('users')->count());
        $this->assertNull($actor->fresh()->avatar_key);
        $this->assertSame([], glob($this->root.'/*'));
    }

    private function multipart(array $parts): array
    {
        $boundary = 'kids-private-avatar-fixture';
        $body = '';
        foreach ($parts as $part) {
            $body .= "--{$boundary}\r\n";
            if ($part[0] === 'field') {
                $body .= "Content-Disposition: form-data; name=\"{$part[1]}\"\r\n\r\n{$part[2]}\r\n";
            } else {
                $body .= "Content-Disposition: form-data; name=\"{$part[1]}\"; filename=\"{$part[2]}\"\r\nContent-Type: application/octet-stream\r\n\r\n{$part[3]}\r\n";
            }
        }

        return ['raw' => $body."--{$boundary}--\r\n", 'headers' => [
            'Content-Type' => 'multipart/form-data; boundary='.$boundary,
            'X-Identity-Test-Mode' => 'users-probe-avatar-validation',
        ]];
    }
}
