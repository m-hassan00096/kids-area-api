<?php

namespace Modules\Identity\Services;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

final class InitialUserData
{
    public function prepare(#[SensitiveParameter] array $input): array
    {
        foreach (['email', 'phone'] as $field) {
            if (($input[$field] ?? null) === '') {
                $input[$field] = null;
            }

            // Laravel skips non-implicit validation rules for whitespace-only strings.
            if (is_string($input[$field] ?? null) && trim($input[$field]) === '') {
                throw ValidationException::withMessages([$field => __('identity.invalid_contact')]);
            }
        }

        $password = $input['password'] ?? null;
        if (! is_string($password) || mb_strlen($password) < 8 || $password !== ($input['password_confirmation'] ?? null)) {
            throw ValidationException::withMessages(['password' => __('identity.invalid_password')]);
        }

        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'regex:/\A[A-Za-z0-9._-]{3,50}\z/'],
            'email' => ['nullable', 'string', 'email:rfc', 'max:254'],
            'phone' => ['nullable', 'string', 'regex:/\A[0-9]{11}\z/'],
        ])->validate();

        return [
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($password),
            'is_active' => true,
        ];
    }
}
