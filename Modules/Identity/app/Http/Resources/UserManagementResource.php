<?php

namespace Modules\Identity\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Identity\Models\User;

final class UserManagementResource extends JsonResource
{
    public function __construct(User $user, private readonly ?object $role, private readonly bool $detail = false)
    {
        parent::__construct($user);
    }

    public function toArray(Request $request): array
    {
        $user = $this->resource;
        $role = $this->role;
        $data = [
            'userId' => (int) $user->id,
            'avatarUrl' => $user->avatar_key === null ? null : '/api/v1/users/'.$user->id.'/avatar',
            'name' => $user->name,
            'username' => $user->username,
            'phone' => $user->phone,
            'isActive' => (bool) $user->is_active,
            'role' => $role === null ? null : [
                'id' => (int) $role->id,
                'name' => $role->name,
                'aliasName' => $role->alias_name,
                'isActive' => (bool) $role->is_active,
            ],
        ];
        if ($this->detail) {
            $data['email'] = $user->email;
        }

        return $data;
    }
}
