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
     * Caller owns manager -> ordered access rows -> user/session locks and
     * authorization. Future authorized administrative reset can reuse this.
     */
    public function replace(#[SensitiveParameter] User $locked, #[SensitiveParameter] string $hash): int
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Credential revocation requires the caller-owned Identity transaction.');
        }
        if ($locked->auth_version < 1 || $locked->auth_version >= PHP_INT_MAX
            || password_get_info($hash)['algoName'] !== 'argon2id') {
            throw new RuntimeException('Invalid credential generation or hash.');
        }

        return SensitiveQueryScope::run(DB::connection(), function () use ($locked, $hash): int {
            $updated = DB::table('users')->where('id', $locked->id)
                ->where('auth_version', $locked->auth_version)
                ->update(['password' => $hash, 'auth_version' => $locked->auth_version + 1, 'updated_at' => now()]);
            if ($updated !== 1) {
                throw new RuntimeException('Credential generation was not updated.');
            }

            return DB::table('sessions')->where('auth_user_id', $locked->id)->delete();
        });
    }
}
