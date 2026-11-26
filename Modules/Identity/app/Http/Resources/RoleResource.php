<?php

namespace Modules\Identity\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Identity\Models\Role;

final class RoleResource extends JsonResource
{
    public function __construct(Role $role, private readonly int $usersCount, private readonly int $permissionsCount, private readonly ?array $permissions = null)
    {
        parent::__construct($role);
    }

    public function toArray(Request $request): array
    {
        $role = $this->resource;
        $data = [
            'roleId' => (int) $role->id,
            'name' => $role->name,
            'usersCount' => $this->usersCount,
            'permissionsCount' => $this->permissionsCount,
            'isActive' => (bool) $role->is_active,
            'isProtected' => in_array($role->name, ['manager', 'reception'], true),
        ];
        if ($this->permissions !== null) {
            $data['permissions'] = $this->permissions;
        }

        return $data;
    }
}
