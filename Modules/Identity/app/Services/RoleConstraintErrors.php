<?php

namespace Modules\Identity\Services;

use App\Support\ApiResponse;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

final class RoleConstraintErrors
{
    public function translate(#[SensitiveParameter] QueryException $exception): QueryException|ValidationException
    {
        if ($exception->getConnectionName() === config('database.default')
            && ($exception->errorInfo[0] ?? null) === '23000'
            && (int) ($exception->errorInfo[1] ?? 0) === 1062
            && preg_match('/\A(?:insert\s+into|update)\s+\x60?roles\x60?\s/i', strtolower(ltrim($exception->getSql()))) === 1
            && preg_match("/ for key '([^']+)'\\z/", (string) ($exception->errorInfo[2] ?? ''), $match) === 1
            && in_array($match[1], ['roles_name_guard_name_unique', 'roles.roles_name_guard_name_unique'], true)) {
            return ValidationException::withMessages(['name' => ApiResponse::text('identity.role_duplicate')]);
        }

        return $exception;
    }
}
