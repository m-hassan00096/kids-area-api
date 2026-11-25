<?php

namespace Modules\Identity\Services;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use SensitiveParameter;

final class InitialUserData
{
    public function prepare(#[SensitiveParameter] array $input): array
    {
        foreach (['email', 'phone'] as $field) {
            if (($input[$field] ?? null) === '') {
                $input[$field] = null;
            }
        }

        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'regex:/\A[A-Za-z0-9._-]{3,50}\z/'],
            'email' => ['nullable', 'string', 'email:rfc', 'max:254'],
            'phone' => ['nullable', 'string', 'regex:/\A[0-9]{11}\z/'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ])->validate();

        return [
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'is_active' => true,
        ];
    }
}
