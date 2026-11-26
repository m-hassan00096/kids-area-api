<?php

namespace Modules\Identity\Actions;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\Identity\Models\Role;
use Modules\Identity\Models\User;
use Modules\Identity\Services\IdentityAccessMutation;
use Modules\Identity\Services\RoleMutationData;
use RuntimeException;

final readonly class CreateRoleAction
{
    public function __construct(private IdentityAccessMutation $mutation, private RoleMutationData $data) {}

    public function execute(Request $request, array $fields): void
    {
        $this->mutation->executeRoles($request, 'identity.roles.store', function () use ($fields): Collection {
            $this->data->name($fields['name']);

            return $this->data->permissions($fields['permissions']);
        }, static function (User $actor, Collection $permissions) use ($fields): void {
            $role = Role::query()->create(['name' => $fields['name'], 'guard_name' => 'web', 'is_active' => $fields['isActive']]);
            if (! $role->exists) {
                throw new RuntimeException('Unable to create Role.');
            }
            $role->syncPermissions($permissions);
        });
    }
}
