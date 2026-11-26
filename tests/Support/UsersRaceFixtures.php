<?php

namespace Tests\Support;

use Modules\Identity\Models\Role;
use Modules\Identity\Models\User;

trait UsersRaceFixtures
{
    private function raceOptions(string $mode, string $barrier): array
    {
        return ['headers' => ['X-Identity-Test-Mode' => $mode, 'X-Identity-Test-Barrier' => $barrier]];
    }

    private function fields(User $target, string $role = 'reception', bool $active = true): array
    {
        return ['name' => $target->name, 'username' => $target->username, 'email' => $target->email, 'phone' => $target->phone,
            'roleId' => Role::findByName($role, 'web')->id, 'isActive' => $active];
    }

    private function createFields(string $username): array
    {
        return ['name' => 'Synthetic new', 'username' => $username, 'roleId' => Role::findByName('reception', 'web')->id,
            'password' => 'synthetic-creation-password', 'passwordConfirmation' => 'synthetic-creation-password'];
    }

    private function upload(array $fields, string $bytes, string $mode, string $barrier = ''): array
    {
        $boundary = 'kids-users-race-fixture';
        $body = '';
        foreach ($fields as $name => $value) {
            $value = is_bool($value) ? ($value ? 'true' : 'false') : $value;
            $body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"{$name}\"\r\n\r\n{$value}\r\n";
        }
        $body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"avatar\"; filename=\"fixture.png\"\r\nContent-Type: image/png\r\n\r\n{$bytes}\r\n";
        $headers = ['Content-Type' => 'multipart/form-data; boundary='.$boundary, 'X-Identity-Test-Mode' => $mode, 'X-Identity-Test-Inspect' => '1'];
        if ($barrier !== '') {
            $headers['X-Identity-Test-Barrier'] = $barrier;
        }

        return ['raw' => $body."--{$boundary}--\r\n", 'headers' => $headers];
    }
}
