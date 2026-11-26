<?php

namespace Modules\Identity\Actions;

use App\Exceptions\BusinessException;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Modules\Identity\Models\User;
use Modules\Identity\Services\Avatar\AvatarLifecycle;
use Modules\Identity\Services\Avatar\NormalizedAvatar;
use Modules\Identity\Services\IdentityAccessMutation;
use Modules\Identity\Services\UserRoleAssignment;
use Modules\Identity\Support\UsersMutationOutcome;
use RuntimeException;
use SensitiveParameter;

final readonly class CreateUserAction
{
    public function __construct(
        private IdentityAccessMutation $mutation,
        private UserRoleAssignment $roles,
        private AvatarLifecycle $avatars,
    ) {}

    public function execute(#[SensitiveParameter] Request $request, #[SensitiveParameter] array $fields, #[SensitiveParameter] ?NormalizedAvatar $image): void
    {
        $hash = Hash::make($fields['password']);
        if (password_get_info($hash)['algoName'] !== 'argon2id') {
            throw new RuntimeException('Unexpected credential hash algorithm.');
        }
        unset($fields['password']);
        $candidate = $image === null ? null : $this->avatars->stage($image);
        $outcome = new UsersMutationOutcome;
        try {
            $this->mutation->executeUsers($request, 'identity.users.create', function () use ($fields, $hash, $candidate): void {
                if (User::query()->whereKey(1)->lockForUpdate()->first(['id']) === null) {
                    throw new BusinessException(ApiResponse::text('identity.founder_id'), 409, 'IDENTITY_FOUNDER_REQUIRED');
                }
                $role = $this->roles->requested($fields['roleId']);
                $user = new User([
                    'name' => $fields['name'],
                    'username' => $fields['username'],
                    'email' => $fields['email'],
                    'phone' => $fields['phone'],
                    'password' => $hash,
                    'is_active' => true,
                ]);
                $user->avatar_key = $candidate;
                if (! $user->save() || $user->id <= 1) {
                    throw new RuntimeException('Unable to create a non-founder User.');
                }
                $user->syncRoles([$role]);
            }, $outcome);
        } finally {
            $this->avatars->finish($candidate, null, $outcome->fileOutcome());
        }
    }
}
