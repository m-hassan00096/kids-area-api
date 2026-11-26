<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\AuthenticationRaceHarness;
use Tests\Support\AvatarFixtures;
use Tests\Support\UsersRaceFixtures;

class UsersAvatarFkRaceTest extends AuthenticationHttpTestCase
{
    use AuthenticationRaceHarness, UsersRaceFixtures;

    public function test_competing_replacements_and_delete_preserve_only_current_references(): void
    {
        $founder = $this->user('Fixture.Founder');
        $actor = $this->user('Fixture.Actor');
        $a = $this->browser($this->server());
        $b = $this->browser($this->server());
        $this->assertSame(200, $a->login($founder->username)->status);
        $this->assertSame(200, $b->login($actor->username)->status);
        foreach (['replace', 'delete', 'delete-first'] as $mode) {
            $target = $this->user('Fixture.Target.'.$mode, 'reception');
            $path = '/api/v1/users/'.$target->id;
            $fields = $this->fields($target) + ['_method' => 'PUT'];
            $this->assertSame(200, $a->request('POST', $path, options: $this->upload($fields, AvatarFixtures::image('png'), 'observe'))->status);
            $old = $target->fresh()->avatar_key;
            $firstUpload = $a->prepare('POST', $path, options: $this->upload($fields, AvatarFixtures::image('png', 16, 8),
                $mode === 'delete-first' ? 'observe' : 'hold-users-update', $mode === 'delete-first' ? 'second' : 'first'));
            $other = $mode === 'replace'
                ? $b->prepare('POST', $path, options: $this->upload($fields, AvatarFixtures::image('png', 24, 8), 'observe', 'second'))
                : $b->prepare('DELETE', $path, options: $this->raceOptions($mode === 'delete-first' ? 'hold-users-delete-fk' : 'observe', $mode === 'delete-first' ? 'first' : 'second'));
            [$first, $second] = $mode === 'delete-first' ? $this->race($other, $firstUpload, 'manager') : $this->race($firstUpload, $other, 'manager');
            $this->assertSame(200, $first->status);
            $this->assertSame($mode === 'delete-first' ? 404 : 200, $second->status);
            $this->assertFileDoesNotExist($this->barrierDir.'/avatars/'.$old);
            if ($mode === 'replace') {
                $key = $target->fresh()->avatar_key;
                $this->assertCount(1, glob($this->barrierDir.'/avatars/*'));
                $read = $a->request('GET', $path.'/avatar');
                $this->assertSame(200, $read->status);
                $this->assertSame(file_get_contents($this->barrierDir.'/avatars/'.$key), $read->body);
                $this->assertSame([24, 8], array_slice(getimagesizefromstring($read->body), 0, 2));
                $this->assertSame(200, $a->request('DELETE', $path)->status);
            } else {
                $this->assertNull($target->fresh());
            }
            $this->assertSame([], glob($this->barrierDir.'/avatars/*'));
        }
    }

    public function test_authorized_read_losing_old_key_reloads_once_or_finishes_immutable_bytes(): void
    {
        $founder = $this->user('Fixture.Founder');
        $actor = $this->user('Fixture.Actor');
        $a = $this->browser($this->server());
        $b = $this->browser($this->server());
        $this->assertSame(200, $a->login($founder->username)->status);
        $this->assertSame(200, $b->login($actor->username)->status);
        foreach (['replace-before', 'delete-before', 'delete-after'] as $mode) {
            $target = $this->user('Fixture.Target.'.$mode, 'reception');
            $path = '/api/v1/users/'.$target->id;
            $fields = $this->fields($target) + ['_method' => 'PUT'];
            $this->assertSame(200, $a->request('POST', $path, options: $this->upload($fields, AvatarFixtures::image('png'), 'observe'))->status);
            $old = $target->fresh()->avatar_key;
            $oldBytes = file_get_contents($this->barrierDir.'/avatars/'.$old);
            $read = $a->prepare('GET', $path.'/avatar', options: $this->raceOptions($mode === 'delete-after' ? 'hold-avatar-after-read' : 'hold-avatar-before-read', 'first'));
            $write = $mode === 'replace-before'
                ? $b->prepare('POST', $path, options: $this->upload($fields, AvatarFixtures::image('png', 24, 8), 'observe', 'second'))
                : $b->prepare('DELETE', $path, options: $this->raceOptions('observe', 'second'));
            [$first, $second] = $this->race($read, $write, null);
            $this->assertSame(200, $second->status);
            $this->assertFileDoesNotExist($this->barrierDir.'/avatars/'.$old);
            if ($mode === 'replace-before') {
                $this->assertSame(200, $first->status);
                $this->assertSame(file_get_contents($this->barrierDir.'/avatars/'.$target->fresh()->avatar_key), $first->body);
                $this->assertSame(200, $a->request('DELETE', $path)->status);
            } elseif ($mode === 'delete-before') {
                $this->assertSame(404, $first->status);
            } else {
                $this->assertSame(200, $first->status);
                $this->assertSame($oldBytes, $first->body);
            }
            $this->assertSame([], glob($this->barrierDir.'/avatars/*'));
        }
    }

    public function test_real_fk_insert_and_delete_both_orders(): void
    {
        $founder = $this->user('Fixture.Founder');
        $a = $this->browser($this->server());
        $b = $this->browser($this->server());
        $this->assertSame(200, $a->login($founder->username)->status);
        Schema::create('fixture_user_links', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id', 'fixture_user_protected_fk')->references('id')->on('users')->restrictOnDelete();
        });
        try {
            foreach ([true, false] as $insertFirst) {
                $target = $this->user('Fixture.Target'.($insertFirst ? '.Linked' : '.Deleted'), 'reception');
                $path = '/api/v1/users/'.$target->id;
                $this->assertSame(200, $a->request('POST', $path, options: $this->upload($this->fields($target) + ['_method' => 'PUT'], AvatarFixtures::image('png'), 'observe'))->status);
                $key = $target->fresh()->avatar_key;
                $insert = $b->prepare('POST', '/api/v1/__fixture-user-link/'.$target->id, options: $this->raceOptions($insertFirst ? 'hold-fk-insert' : 'observe', $insertFirst ? 'first' : 'second'));
                $delete = $a->prepare('DELETE', $path, options: $this->raceOptions($insertFirst ? 'users-action-protected-fk' : 'hold-users-delete-fk', $insertFirst ? 'second' : 'first'));
                [$first, $second] = $insertFirst ? $this->race($insert, $delete, 'users') : $this->race($delete, $insert, 'fk');
                $this->assertSame($insertFirst ? 201 : 200, $first->status);
                $this->assertSame(409, $second->status);
                if ($insertFirst) {
                    $this->assertSame('IDENTITY_USER_LINKED', $second->json()['errors']['code']);
                    $this->assertNotNull($target->fresh());
                    $this->assertSame(1, DB::table('fixture_user_links')->where('user_id', $target->id)->count());
                    $this->assertFileExists($this->barrierDir.'/avatars/'.$key);
                } else {
                    $this->assertSame('parent_missing', $second->json()['fixture']);
                    $this->assertNull($target->fresh());
                    $this->assertSame(0, DB::table('fixture_user_links')->where('user_id', $target->id)->count());
                    $this->assertFileDoesNotExist($this->barrierDir.'/avatars/'.$key);
                }
            }
        } finally {
            Schema::drop('fixture_user_links');
        }
    }

    public function test_real_innodb_deadlock_aborts_users_attempt_once_without_losing_file(): void
    {
        $founder = $this->user('Fixture.Founder');
        $target = $this->user('Fixture.Target', 'reception');
        $admin = $this->browser($this->server());
        $external = $this->browser($this->server());
        $this->assertSame(200, $admin->login($founder->username)->status);
        $path = '/api/v1/users/'.$target->id;
        $fields = $this->fields($target) + ['_method' => 'PUT'];
        $this->assertSame(200, $admin->request('POST', $path, options: $this->upload($fields, AvatarFixtures::image('png'), 'observe'))->status);
        $before = $target->fresh()->getAttributes();
        $key = $before['avatar_key'];
        $bytes = file_get_contents($this->barrierDir.'/avatars/'.$key);
        Schema::create('fixture_user_links', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id', 'fixture_user_protected_fk')->references('id')->on('users')->restrictOnDelete();
        });
        try {
            $deadlocks = (int) DB::selectOne("SHOW GLOBAL STATUS LIKE 'Innodb_deadlocks'")->Value;
            [$first, $second] = $this->race(
                $external->prepare('POST', '/api/v1/__fixture-user-link/'.$target->id, options: $this->raceOptions('hold-fk-deadlock', 'first')),
                $admin->prepare('POST', $path, options: $this->upload(array_replace($fields, ['name' => 'Must not retry']), AvatarFixtures::image('png', 16, 8), 'observe', 'second')),
                'users',
            );
            $this->assertSame(201, $first->status);
            $this->assertSame(503, $second->status, $second->header('X-Test-Exception-Class') ?? '');
            $this->assertSame('rolled_back', $second->header('X-Test-Users-Outcome'));
            $this->assertSame('1', $second->header('X-Test-Redacted'));
            $this->assertSame($deadlocks + 1, (int) DB::selectOne("SHOW GLOBAL STATUS LIKE 'Innodb_deadlocks'")->Value);
            $this->assertSame(100, DB::table('fixture_user_links')->where('user_id', $target->id)->count());
            $this->assertSame($before, $target->fresh()->getAttributes());
            $this->assertSame($bytes, file_get_contents($this->barrierDir.'/avatars/'.$key));
            $this->assertCount(1, glob($this->barrierDir.'/avatars/*'));
            $this->assertSame(200, $admin->me()->status);
        } finally {
            Schema::drop('fixture_user_links');
        }
    }
}
