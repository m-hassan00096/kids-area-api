<?php

namespace Modules\Identity\Actions;

use Illuminate\Http\Request;
use Modules\Identity\Models\User;
use Modules\Identity\Services\Avatar\AvatarLifecycle;
use Modules\Identity\Services\CredentialRevocation;
use Modules\Identity\Services\IdentityAccessMutation;
use Modules\Identity\Services\UserRoleAssignment;
use Modules\Identity\Support\UsersMutationOutcome;
use RuntimeException;
use SensitiveParameter;

final readonly class DeleteUserAction
{
    public function __construct(
        private IdentityAccessMutation $mutation,
        private UserRoleAssignment $roles,
        private CredentialRevocation $revocation,
        private AvatarLifecycle $avatars,
        // No business references exist in the current schema. Future owning
        // modules must register reviewed, named restrictive FK contracts.
        private array $protectedUserForeignKeys = [],
    ) {}

    public function execute(#[SensitiveParameter] Request $request): void
    {
        $snapshot = $request->attributes->get('users_target');
        $binding = $request->attributes->get('staff_binding');
        abort_unless($snapshot instanceof User && is_object($binding), 403);
        $this->protect($snapshot, (int) $binding->auth_user_id);
        $outcome = new UsersMutationOutcome;
        $old = null;
        try {
            $this->mutation->executeUsers($request, 'identity.users.delete', function (User $actor, User $target) use (&$old): void {
                $this->protect($target, $actor->id);
                $this->roles->current($target);
                $old = $target->avatar_key;
                $this->revocation->deleteSessions($target);
                // Native Spatie model deletion detaches only this model's
                // memberships. InnoDB RESTRICT remains the authority.
                if ($target->delete() !== true) {
                    throw new RuntimeException('Unable to delete User.');
                }
            }, $outcome, $this->protectedUserForeignKeys);
        } finally {
            $this->avatars->finish(null, $old, $outcome->fileOutcome());
        }
    }

    private function protect(#[SensitiveParameter] User $target, int $actorId): void
    {
        abort_if($target->id === 1 || $target->id === $actorId, 403);
    }
}
