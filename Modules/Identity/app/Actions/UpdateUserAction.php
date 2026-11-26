<?php

namespace Modules\Identity\Actions;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Modules\Identity\Models\User;
use Modules\Identity\Services\Avatar\AvatarLifecycle;
use Modules\Identity\Services\Avatar\NormalizedAvatar;
use Modules\Identity\Services\CredentialRevocation;
use Modules\Identity\Services\IdentityAccessMutation;
use Modules\Identity\Services\StaffSession;
use Modules\Identity\Services\UserRoleAssignment;
use Modules\Identity\Support\UsersMutationOutcome;
use RuntimeException;
use SensitiveParameter;

final readonly class UpdateUserAction
{
    public function __construct(
        private IdentityAccessMutation $mutation,
        private UserRoleAssignment $roles,
        private CredentialRevocation $revocation,
        private AvatarLifecycle $avatars,
        private StaffSession $sessions,
    ) {}

    public function execute(#[SensitiveParameter] Request $request, #[SensitiveParameter] array $fields, #[SensitiveParameter] ?NormalizedAvatar $image): void
    {
        $snapshot = $request->attributes->get('users_target');
        $binding = $request->attributes->get('staff_binding');
        abort_unless($snapshot instanceof User && is_object($binding), 403);
        $this->protect($snapshot, (int) $binding->auth_user_id, $fields['isActive'], $fields['password'] !== null);

        $hash = $fields['password'] === null ? null : Hash::make($fields['password']);
        if ($hash !== null && password_get_info($hash)['algoName'] !== 'argon2id') {
            throw new RuntimeException('Unexpected credential hash algorithm.');
        }
        unset($fields['password']);
        $candidate = $image === null ? null : $this->avatars->stage($image);
        $outcome = new UsersMutationOutcome;
        $old = null;
        $selfRevoked = false;
        try {
            $this->mutation->executeUsers($request, 'identity.users.update', function (User $actor, User $target) use ($fields, $hash, $candidate, &$old, &$selfRevoked): void {
                $this->protect($target, $actor->id, $fields['isActive'], $hash !== null);
                $current = $this->roles->current($target);
                $role = $this->roles->requested($fields['roleId'], $current, ! $target->is_active && $fields['isActive']);
                $disable = $target->is_active && ! $fields['isActive'];

                $target->fill([
                    'name' => $fields['name'],
                    'username' => $fields['username'],
                    'email' => $fields['email'],
                    'phone' => $fields['phone'],
                    'is_active' => $fields['isActive'],
                ]);
                if ($candidate !== null || $fields['removeAvatar']) {
                    $old = $target->avatar_key;
                    $target->avatar_key = $candidate;
                }
                if (! $target->save()) {
                    throw new RuntimeException('Unable to update User.');
                }
                if ($current?->id !== $role->id) {
                    $target->syncRoles([$role]);
                }

                // A combined disable and reset advances the generation once.
                if ($disable || $hash !== null) {
                    $this->revocation->revoke($target, $hash);
                    $selfRevoked = $actor->id === $target->id;
                }
            }, $outcome);
        } finally {
            $this->avatars->finish($candidate, $old, $outcome->fileOutcome());
        }
        if ($outcome->state === 'committed' && $selfRevoked) {
            $this->sessions->clearRevokedBrowser($request);
        }
    }

    private function protect(#[SensitiveParameter] User $target, int $actorId, bool $active, bool $reset): void
    {
        abort_if($target->id === 1 && ($actorId !== 1 || ! $active), 403);
        abort_if($target->id === $actorId && $reset, 403);
    }
}
