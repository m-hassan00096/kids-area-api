<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Modules\Identity\Models\Role;
use Modules\Identity\Models\User;
use Tests\Support\AuthenticationRaceHarness;
use Tests\Support\AvatarFixtures;
use Tests\Support\UsersRaceFixtures;

class UsersUniquenessRaceTest extends AuthenticationHttpTestCase
{
    use AuthenticationRaceHarness, UsersRaceFixtures;

    public function test_overlapping_create_and_update_collisions_use_real_mysql_constraints(): void
    {
        $founder = $this->user('Fixture.Founder');
        $actor = $this->user('Fixture.Actor');
        $a = $this->browser($this->server());
        $b = $this->browser($this->server());
        $this->assertSame(200, $a->login($founder->username)->status);
        $this->assertSame(200, $b->login($actor->username)->status);
        foreach (['username', 'email', 'phone'] as $i => $field) {
            $target = $this->user('Fixture.Target'.$i, 'reception');
            $create = $this->createFields('Fixture.New'.$i);
            $update = $this->fields($target) + ['_method' => 'PUT'];
            $value = match ($field) {
                'username' => 'Fixture.Duplicate',
                'email' => 'Exact'.$i.'@Example.test',
                'phone' => '0001234567'.$i,
            };
            $create[$field] = $value;
            $update[$field] = $field === 'email' ? strtolower($value) : $value;
            $before = $target->fresh()->getAttributes();
            $count = User::count();
            $oldFiles = glob($this->barrierDir.'/avatars/*');
            [$first, $second] = $this->race(
                $a->prepare('POST', '/api/v1/users', options: $this->upload($create, AvatarFixtures::image('png', 16, 8), 'hold-users-insert', 'first')),
                $b->prepare('POST', '/api/v1/users/'.$target->id, options: $this->upload($update, AvatarFixtures::image('png', 24, 8), 'observe', 'second')),
                'manager',
            );
            $this->assertSame(201, $first->status);
            $this->assertSame(422, $second->status);
            $this->assertArrayHasKey($field, $second->json()['errors']);
            $this->assertSame('rolled_back', $second->header('X-Test-Users-Outcome'));
            $this->assertSame('1', $second->header('X-Test-Redacted'));
            $this->assertSame($before, $target->fresh()->getAttributes());
            $this->assertSame($count + 1, User::count());
            $created = User::where('username', $create['username'])->sole();
            $this->assertSame($value, $created->{$field});
            $this->assertSame('reception', $created->roles()->sole()->name);
            $this->assertSame('reception', $target->fresh()->roles()->sole()->name);
            $this->assertFileExists($this->barrierDir.'/avatars/'.$created->avatar_key);
            $this->assertCount(count($oldFiles) + 1, glob($this->barrierDir.'/avatars/*'));
        }
    }

    public function test_competing_creates_and_competing_updates_are_serialized(): void
    {
        $founder = $this->user('Fixture.Founder');
        $actor = $this->user('Fixture.Actor');
        $a = $this->browser($this->server());
        $b = $this->browser($this->server());
        $this->assertSame(200, $a->login($founder->username)->status);
        $this->assertSame(200, $b->login($actor->username)->status);
        $fields = $this->createFields('Fixture.Concurrent');
        [$first, $second] = $this->race(
            $a->prepare('POST', '/api/v1/users', $fields, $this->raceOptions('hold-users-insert', 'first')),
            $b->prepare('POST', '/api/v1/users', $fields, $this->raceOptions('observe', 'second')),
            'manager',
        );
        $this->assertSame(201, $first->status);
        $this->assertSame(422, $second->status);
        $this->assertSame('rolled_back', $second->header('X-Test-Users-Outcome'));
        $this->assertSame(1, User::where('username', 'Fixture.Concurrent')->count());
        $target = User::where('username', 'Fixture.Concurrent')->sole();
        $other = $this->user('Fixture.Other', 'reception');
        $one = array_replace($this->fields($target), ['email' => 'Race@Example.test']);
        $two = array_replace($this->fields($other), ['email' => 'race@example.test']);
        [$first, $second] = $this->race(
            $a->prepare('PUT', '/api/v1/users/'.$target->id, $one, $this->raceOptions('hold-users-update', 'first')),
            $b->prepare('PUT', '/api/v1/users/'.$other->id, $two, $this->raceOptions('observe', 'second')),
            'manager',
        );
        $this->assertSame(200, $first->status);
        $this->assertSame(422, $second->status);
        $this->assertArrayHasKey('email', $second->json()['errors']);
        $this->assertSame('Race@Example.test', $target->fresh()->email);
        $this->assertNull($other->fresh()->email);

        $role = Role::create(['name' => 'fixture-last-role', 'guard_name' => 'web']);
        $one = array_replace($this->fields($target->fresh(), 'manager'), ['name' => 'First write']);
        $two = array_replace($this->fields($target->fresh(), $role->name), ['name' => 'Second write']);
        [$first, $second] = $this->race(
            $a->prepare('PUT', '/api/v1/users/'.$target->id, $one, $this->raceOptions('hold-users-update', 'first')),
            $b->prepare('PUT', '/api/v1/users/'.$target->id, $two, $this->raceOptions('observe', 'second')),
            'manager',
        );
        $this->assertSame(200, $first->status);
        $this->assertSame(200, $second->status);
        $this->assertSame('Second write', $target->fresh()->name);
        $this->assertSame([$role->id], $target->fresh()->roles()->pluck('roles.id')->all());
        $this->assertSame(1, $target->fresh()->auth_version);
        $this->assertSame(0, DB::table('model_has_permissions')->where('model_id', $target->id)->count());
    }
}
