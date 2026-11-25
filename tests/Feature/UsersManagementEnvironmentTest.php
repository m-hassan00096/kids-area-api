<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;

class UsersManagementEnvironmentTest extends AuthenticationHttpTestCase
{
    public function test_guarded_users_management_fixtures_are_isolated(): void
    {
        // Parent performs migrate:fresh only after validating the dedicated test database.
        $this->assertTrue(app()->environment('testing'));
        $this->assertSame('127.0.0.1', DB::connection()->getConfig('host'));
        $this->assertSame(0, DB::table('users')->count());
        $this->assertSame(0, DB::table('sessions')->count());
        $identity = DB::selectOne('SELECT DATABASE() AS db, CURRENT_USER() AS principal, VERSION() AS version, @@default_storage_engine AS engine');
        $this->assertSame('kids_area_test', $identity->db);
        $this->assertStringStartsWith('kids_area_ci@', $identity->principal);
        $this->assertStringStartsWith('8.', $identity->version);
        $this->assertSame('InnoDB', $identity->engine);

        $manager = $this->user('Fixture.Manager');
        $reception = $this->user('Fixture.Reception', 'reception');
        $first = $this->browser($this->server());
        $second = $this->browser($this->server());
        $this->assertNotSame($first->server->url, $second->server->url);
        $this->assertSame($first->timeMs, $second->timeMs);
        $this->assertSame(200, $first->login($manager->username)->status);
        $this->assertSame(200, $second->login($reception->username)->status);
        $this->assertTrue($first->sessionId() !== $second->sessionId());
        $this->assertSame($manager->username, $first->me()->json()['data']['username']);
        $this->assertSame($reception->username, $second->me()->json()['data']['username']);
        $this->assertSame($manager->id, (int) $this->binding($first)->auth_user_id);
        $this->assertSame($reception->id, (int) $this->binding($second)->auth_user_id);
        $this->assertSame(2, DB::table('sessions')->whereNotNull('auth_user_id')->count());
        $this->assertSame(0700, fileperms($this->barrierDir) & 0777);

        // A synthetic private root only; the actual avatar disk is delivered in a later task.
        $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'kids-users-'.bin2hex(random_bytes(10));
        $this->assertTrue(mkdir($root, 0700));
        $file = $root.DIRECTORY_SEPARATOR.'synthetic.png';
        $umask = umask(0077);
        try {
            $this->assertFalse(str_starts_with(realpath($root).DIRECTORY_SEPARATOR, realpath(public_path()).DIRECTORY_SEPARATOR));
            $this->assertSame(0700, fileperms($root) & 0777);
            $image = imagecreatetruecolor(8, 8);
            $this->assertInstanceOf(\GdImage::class, $image);
            $this->assertTrue(imagepng($image, $file));
            unset($image);
            $this->assertSame(0600, fileperms($file) & 0777);
            $this->assertSame('image/png', (new \finfo(FILEINFO_MIME_TYPE))->file($file));
        } finally {
            umask($umask);
            if (is_file($file)) {
                unlink($file);
            }
            rmdir($root);
        }
        $this->assertDirectoryDoesNotExist($root);
    }
}
