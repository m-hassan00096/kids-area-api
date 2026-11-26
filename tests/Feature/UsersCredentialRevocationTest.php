<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use LogicException;
use Modules\Identity\Models\User;
use Modules\Identity\Services\CredentialRevocation;
use Modules\Identity\Services\IdentityMutex;
use RuntimeException;

class UsersCredentialRevocationTest extends AuthenticationHttpTestCase
{
    public function test_generation_only_and_hash_replacement_revoke_only_target_sessions(): void
    {
        $target = $this->user('Fixture.Target');
        $other = $this->user('Fixture.Other');
        $server = $this->server();
        $a = $this->browser($server);
        $b = $this->browser($server);
        $c = $this->browser($server);
        $this->assertSame(200, $a->login($target->username)->status);
        $this->assertSame(200, $b->login($target->username)->status);
        $this->assertSame(200, $c->login($other->username)->status);
        $service = app(CredentialRevocation::class);
        try {
            $service->revoke($target);
            $this->fail('Expected outer transaction guard.');
        } catch (LogicException) {
            $this->assertSame(1, $target->fresh()->auth_version);
        }
        DB::transaction(function () use ($target, $service): void {
            app(IdentityMutex::class)->lock();
            $locked = User::whereKey($target->id)->lockForUpdate()->sole();
            $this->assertSame(2, $service->revoke($locked));
        }, 1);
        $this->assertSame(2, $target->fresh()->auth_version);
        $this->assertSame($target->password, $target->fresh()->password);
        $this->assertSame(401, $a->me()->status);
        $this->assertSame(401, $b->me()->status);
        $this->assertSame(200, $c->me()->status);
        $this->assertSame(200, $a->login($target->username)->status);
        $hash = Hash::make('new-synthetic-password');
        DB::transaction(function () use ($target, $service, $hash): void {
            app(IdentityMutex::class)->lock();
            $locked = User::whereKey($target->id)->lockForUpdate()->sole();
            $this->assertSame(1, $service->replace($locked, $hash));
        }, 1);
        $this->assertSame(3, $target->fresh()->auth_version);
        $this->assertTrue(Hash::check('new-synthetic-password', $target->fresh()->password));
        $this->assertSame(401, $a->me()->status);
        $this->assertSame(200, $c->me()->status);
    }

    public function test_generation_guard_and_stale_compare_do_not_delete_sessions(): void
    {
        $target = $this->user('Fixture.Target');
        $browser = $this->browser($this->server());
        $this->assertSame(200, $browser->login($target->username)->status);
        $service = app(CredentialRevocation::class);
        foreach (['stale', 'max', 'invalid-hash'] as $mode) {
            try {
                DB::transaction(function () use ($target, $service, $mode): void {
                    app(IdentityMutex::class)->lock();
                    $locked = User::whereKey($target->id)->lockForUpdate()->sole();
                    if ($mode === 'stale') {
                        $locked->auth_version = 2;
                    } elseif ($mode === 'max') {
                        DB::table('users')->where('id', $target->id)->update(['auth_version' => PHP_INT_MAX]);
                        $locked->auth_version = PHP_INT_MAX;
                    }
                    $service->revoke($locked, $mode === 'invalid-hash' ? 'not-a-hash' : null);
                }, 1);
                $this->fail('Expected invalid generation or hash.');
            } catch (RuntimeException) {
                $this->assertSame(1, $target->fresh()->auth_version);
                $this->assertSame($target->password, $target->fresh()->password);
                $this->assertSame(1, DB::table('sessions')->where('auth_user_id', $target->id)->count());
            }
        }
        $this->assertSame(200, $browser->me()->status);
    }
}
