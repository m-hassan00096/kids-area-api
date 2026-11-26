<?php

namespace Modules\Identity\Services;

use App\Support\SensitiveQueryScope;
use Illuminate\Support\Facades\DB;
use LogicException;
use Modules\Identity\Models\User;
use RuntimeException;
use SensitiveParameter;

final class CredentialRevocation
{
    /**
     * Preserve the Profile contract. Caller owns manager -> ordered access
     * rows -> user/session locks and authorization.
     */
    public function replace(#[SensitiveParameter] User $locked, #[SensitiveParameter] string $hash): int
    {
        return $this->revoke($locked, $hash);
    }

    public function revoke(#[SensitiveParameter] User $locked, #[SensitiveParameter] ?string $hash = null): int
    {
        $this->requireTransaction();
        if ($locked->auth_version < 1 || $locked->auth_version >= PHP_INT_MAX
            || ($hash !== null && password_get_info($hash)['algoName'] !== 'argon2id')) {
            throw new RuntimeException('Invalid credential generation or hash.');
        }

        return SensitiveQueryScope::run(DB::connection(), function () use ($locked, $hash): int {
            $values = ['auth_version' => $locked->auth_version + 1, 'updated_at' => now()];
            if ($hash !== null) {
                $values['password'] = $hash;
            }
            $updated = DB::table('users')->where('id', $locked->id)
                ->where('auth_version', $locked->auth_version)->update($values);
            if ($updated !== 1) {
                throw new RuntimeException('Credential generation was not updated.');
            }

            return $this->deleteSessions($locked);
        });
    }

    public function deleteSessions(#[SensitiveParameter] User $locked): int
    {
        $this->requireTransaction();

        return SensitiveQueryScope::run(DB::connection(), fn () => DB::table('sessions')
            ->where('auth_user_id', $locked->id)->delete());
    }

    private function requireTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Credential revocation requires the caller-owned Identity transaction.');
        }
    }
}
