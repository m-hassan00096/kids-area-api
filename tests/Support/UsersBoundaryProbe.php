<?php

namespace Tests\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Modules\Identity\Services\Avatar\AvatarImageNormalizer;
use Modules\Identity\Services\IdentityAccessMutation;
use Modules\Identity\Services\StaffSession;
use Modules\Identity\Support\UsersMutationOutcome;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Spatie\QueryBuilder\Exceptions\InvalidFieldQuery;
use Spatie\QueryBuilder\Exceptions\InvalidFilterQuery;
use Spatie\QueryBuilder\Exceptions\InvalidIncludeQuery;
use Spatie\QueryBuilder\Exceptions\InvalidSortQuery;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class UsersBoundaryProbe
{
    public const KEY = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.png';

    public const PATH = '/private/synthetic-avatar-sentinel.png';

    public const RAW = 'synthetic-multipart-content-sentinel';

    public function index(): JsonResponse
    {
        return $this->execute();
    }

    public function show(): JsonResponse
    {
        return $this->execute();
    }

    public function store(): JsonResponse
    {
        return $this->execute();
    }

    public function update(): JsonResponse
    {
        return $this->execute();
    }

    public function destroy(): JsonResponse
    {
        return $this->execute();
    }

    public function avatar(): JsonResponse
    {
        return $this->execute();
    }

    private function execute(): JsonResponse
    {
        $request = request();
        $mode = $request->headers->get('X-Identity-Test-Mode');
        $body = $request->isJson() ? $request->json()->all() : $request->request->all();
        if (is_string($mode) && str_starts_with($mode, 'users-probe-mutation-')) {
            return $this->mutationProbe($mode);
        }
        if ($mode === 'users-probe-transport') {
            abort(503);
        }
        if ($mode === 'users-probe-avatar-validation') {
            $file = $request->files->get('avatar');
            abort_unless($file instanceof UploadedFile, 422);
            app(AvatarImageNormalizer::class)->fromUpload($file);
            $request->attributes->set('_test_avatar_validated', true);
            abort(503);
        }
        if ($mode === 'users-probe-normalized') {
            $request->attributes->set('_test_users_fingerprint', hash('sha256', serialize([
                $body, $request->query->all(), $request->attributes->get('users_target')?->id,
            ])));
            abort(503);
        }

        $unknown = collect(['synthetic-redaction-sentinel']);
        $allowed = collect(['synthetic-approved']);
        match ($mode) {
            'users-probe-filter' => throw new InvalidFilterQuery($unknown, $allowed),
            'users-probe-sort' => throw new InvalidSortQuery($unknown, $allowed),
            'users-probe-include' => throw new InvalidIncludeQuery($unknown, $allowed),
            'users-probe-fields' => throw new InvalidFieldQuery($unknown, $allowed),
            'users-probe-unknown' => throw new UnknownUsersQueryException(400, 'synthetic-redaction-sentinel'),
            'users-probe-conflict' => abort(409, 'synthetic-redaction-sentinel'),
            default => null,
        };

        // Read-only synthetic binding probe, never a real mutation.
        $values = [$body['password'] ?? self::RAW, $body['email'] ?? self::RAW,
            $body['phone'] ?? self::RAW, self::KEY, self::PATH, self::RAW];
        DB::selectOne('SELECT ? AS synthetic_users_sensitive_probe, ? AS synthetic_email, ? AS synthetic_phone, ? AS synthetic_key, ? AS synthetic_path, ? AS synthetic_raw', $values);
        if ($mode === 'users-probe-fault') {
            throw new RuntimeException(implode(' ', $values));
        }

        abort(503);
    }

    // Only the guarded test HTTP router binds this controller. These synthetic
    // callbacks prove the shared boundary, not the production CRUD actions.
    private function mutationProbe(string $mode): JsonResponse
    {
        $request = request();
        $binding = $request->attributes->get('staff_binding');
        $actorId = (int) $binding->auth_user_id;
        $sessionId = $request->session()->getId();
        $targetId = $request->attributes->get('users_target')->id;
        $connection = DB::connection();
        $connection->getPdo()->exec('SET SESSION innodb_lock_wait_timeout = 9');
        if ($mode === 'users-probe-mutation-stale') {
            DB::table('sessions')->where('id', $sessionId)->update(['auth_last_activity' => app(StaffSession::class)->now() - StaffSession::IDLE_SECONDS]);
        } elseif ($mode === 'users-probe-mutation-revoked') {
            DB::table('sessions')->where('id', $sessionId)->delete();
        } elseif ($mode === 'users-probe-mutation-owner') {
            DB::table('sessions')->where('id', $sessionId)->update(['auth_user_id' => $targetId]);
        } elseif ($mode === 'users-probe-mutation-permission') {
            $roleId = DB::table('model_has_roles')->where('model_id', $actorId)->value('role_id');
            $permissionId = DB::table('permissions')->where('name', 'identity.users.update')->value('id');
            DB::table('role_has_permissions')->where('role_id', $roleId)->where('permission_id', $permissionId)->delete();
        }

        $outcome = new UsersMutationOutcome;
        $calls = 0;
        try {
            app(IdentityAccessMutation::class)->executeUsers($request, 'identity.users.update', function ($actor, $target) use ($mode, &$calls): void {
                $calls++;
                if (DB::transactionLevel() !== 1 || (int) DB::selectOne('SELECT @@SESSION.innodb_lock_wait_timeout AS value')->value !== 5) {
                    throw new RuntimeException('Synthetic boundary did not own the expected transaction.');
                }
                DB::table('users')->where('id', $target->id)->update(['name' => 'Synthetic boundary changed']);
                if ($mode === 'users-probe-mutation-duplicate') {
                    $username = DB::table('users')->where('id', $actor->id)->value('username');
                    DB::table('users')->where('id', $target->id)->update(['username' => $username]);
                } elseif ($mode === 'users-probe-mutation-last-admin') {
                    DB::table('users')->where('id', $actor->id)->update(['is_active' => false]);
                } elseif ($mode === 'users-probe-mutation-permanent') {
                    DB::table('roles')->where('name', 'reception')->update(['is_active' => false]);
                } elseif (in_array($mode, ['users-probe-mutation-rollback', 'users-probe-mutation-rollback-restore'], true)) {
                    throw new RuntimeException('Synthetic rollback fault.');
                } elseif ($mode === 'users-probe-mutation-cache') {
                    app()->forgetInstance(PermissionRegistrar::class);
                    app()->bind(PermissionRegistrar::class, static fn () => throw new RuntimeException('Synthetic cache fault.'));
                }
            }, $outcome);
        } finally {
            $request->attributes->set('_test_mutation_state', $outcome->state);
            $request->attributes->set('_test_mutation_file_outcome', $outcome->fileOutcome());
            $request->attributes->set('_test_mutation_calls', (string) $calls);
            $request->attributes->set('_test_mutation_wait', (string) DB::selectOne('SELECT @@SESSION.innodb_lock_wait_timeout AS value')->value);
        }

        return response()->json(['success' => true]);
    }
}
