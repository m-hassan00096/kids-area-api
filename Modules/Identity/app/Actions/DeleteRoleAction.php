<?php

namespace Modules\Identity\Actions;

use Illuminate\Http\Request;
use Modules\Identity\Models\Role;
use Modules\Identity\Services\IdentityAccessMutation;
use Modules\Identity\Services\RoleMutationData;
use RuntimeException;

final readonly class DeleteRoleAction
{
    public function __construct(private IdentityAccessMutation $mutation, private RoleMutationData $data) {}

    public function execute(Request $request): void
    {
        $this->mutation->executeRoles($request, 'identity.roles.delete', function () use ($request): ?Role {
            $role = $this->data->target($request);
            if ($role !== null) {
                if ($this->data->protected($role)) {
                    $this->data->rejectProtected();
                }
                $this->data->requireUnassigned($role);
                $this->data->permissions([], $role);
            }

            return $role;
        }, static function ($actor, ?Role $role): void {
            abort_if($role === null, 404);
            if ($role->delete() !== true) {
                throw new RuntimeException('Unable to delete Role.');
            }
        });
    }
}
