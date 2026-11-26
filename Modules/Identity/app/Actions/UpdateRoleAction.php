<?php

namespace Modules\Identity\Actions;

use Illuminate\Http\Request;
use Modules\Identity\Models\Role;
use Modules\Identity\Models\User;
use Modules\Identity\Services\CredentialRevocation;
use Modules\Identity\Services\IdentityAccessMutation;
use Modules\Identity\Services\RoleMutationData;
use Modules\Identity\Services\StaffSession;
use RuntimeException;

final readonly class UpdateRoleAction
{
    public function __construct(
        private IdentityAccessMutation $mutation,
        private RoleMutationData $data,
        private CredentialRevocation $revocation,
        private StaffSession $sessions,
    ) {}

    public function execute(Request $request, array $fields): void
    {
        $selfRevoked = $this->mutation->executeRoles($request, 'identity.roles.update', function () use ($request, $fields): array {
            $role = $this->data->target($request);
            if ($role === null) {
                return [null, collect(), collect()];
            }
            if ($this->data->protected($role) && ($role->name !== $fields['name'] || ! $fields['isActive'])) {
                $this->data->rejectProtected();
            }
            $this->data->name($fields['name'], $role);
            $members = $this->data->members($role);
            $permissions = $this->data->permissions($fields['permissions'], $role);

            return [$role, $members, $permissions];
        }, function (User $actor, array $prepared) use ($fields): bool {
            [$role, $members, $permissions] = $prepared;
            abort_unless($role instanceof Role, 404);
            $disable = $role->is_active && ! $fields['isActive'];
            if ($disable) {
                foreach ($members as $member) {
                    if ($member->auth_version < 1 || $member->auth_version >= PHP_INT_MAX) {
                        throw new RuntimeException('Invalid credential generation.');
                    }
                }
            }
            $role->name = $fields['name'];
            $role->is_active = $fields['isActive'];
            if (! $role->save()) {
                throw new RuntimeException('Unable to update Role.');
            }
            $role->syncPermissions($permissions);
            $selfRevoked = false;
            if ($disable) {
                foreach ($members as $member) {
                    $this->revocation->revoke($member);
                    $selfRevoked = $selfRevoked || $member->id === $actor->id;
                }
            }

            return $selfRevoked;
        });

        if ($selfRevoked) {
            $this->sessions->clearRevokedBrowser($request);
        }
    }
}
