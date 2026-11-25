<?php

namespace Modules\Identity\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Modules\Identity\Models\User;

/**
 * Synthetic development/test fixture only. Never invoked by production seeders.
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => 'Test staff',
            'username' => 'staff-'.bin2hex(random_bytes(8)),
            'email' => null,
            'phone' => null,
            'password' => Hash::make('test-fixture-only-password'),
            'is_active' => true,
        ];
    }
}
