<?php

namespace Modules\Identity\Services;

use App\Exceptions\BusinessException;
use App\Support\ApiResponse;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

final class UserConstraintErrors
{
    // Only after proven rollback, never after an uncertain commit.
    public function translate(#[SensitiveParameter] QueryException $exception, array $protectedUserForeignKeys = []): QueryException|ValidationException|BusinessException
    {
        if ($exception->getConnectionName() !== config('database.default')
            || ($exception->errorInfo[0] ?? null) !== '23000') {
            return $exception;
        }

        $sql = strtolower(ltrim($exception->getSql()));
        $message = (string) ($exception->errorInfo[2] ?? '');
        $code = (int) ($exception->errorInfo[1] ?? 0);
        if ($code === 1062 && preg_match('/\A(?:insert\s+into|update)\s+\x60?users\x60?\s/i', $sql) === 1
            && preg_match("/ for key '([^']+)'\\z/", $message, $match) === 1) {
            $field = match ($match[1]) {
                'users_username_unique', 'users.users_username_unique' => 'username',
                'users_email_unique', 'users.users_email_unique' => 'email',
                'users_phone_unique', 'users.users_phone_unique' => 'phone',
                default => null,
            };
            if ($field !== null) {
                return ValidationException::withMessages([$field => ApiResponse::text('identity.user_duplicate')]);
            }
        }
        if ($code === 1451 && preg_match('/\Adelete\s+from\s+\x60?users\x60?\s/i', $sql) === 1
            && preg_match('/CONSTRAINT \x60([A-Za-z0-9_]+)\x60 FOREIGN KEY \([^)]*\) REFERENCES \x60users\x60 \(\x60id\x60\)/', $message, $match) === 1
            && in_array($match[1], $protectedUserForeignKeys, true)) {
            return new BusinessException(ApiResponse::text('identity.user_linked'), 409, 'IDENTITY_USER_LINKED');
        }

        return $exception;
    }
}
