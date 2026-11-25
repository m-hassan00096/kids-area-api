<?php

namespace Modules\Identity\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Modules\Identity\Database\Factories\UserFactory;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, HasRoles;

    protected $guard_name = 'web';

    protected $fillable = [
        'name',
        'username',
        'email',
        'phone',
        'password',
        'is_active',
    ];

    protected $hidden = ['password', 'auth_version'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'auth_version' => 'integer'];
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
