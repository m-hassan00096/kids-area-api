<?php

namespace Modules\Activities\Services;

use App\Exceptions\BusinessException;
use App\Support\ApiResponse;
use Illuminate\Database\QueryException;
use SensitiveParameter;

final class ActivityConstraintErrors
{
    // The coordinating Action calls this only after a proven rollback.
    // Never reinterpret an unrelated SQL error or uncertain commit as a duplicate.
    public function translate(#[SensitiveParameter] QueryException $exception): QueryException|BusinessException
    {
        if ($exception->getConnectionName() === config('database.default')
            && ($exception->errorInfo[0] ?? null) === '23000'
            && (int) ($exception->errorInfo[1] ?? 0) === 1062
            && preg_match('/\A(?:insert\s+into|update)\s+\x60?activities\x60?\s/i', strtolower(ltrim($exception->getSql()))) === 1
            && preg_match("/ for key '([^']+)'\\z/", (string) ($exception->errorInfo[2] ?? ''), $match) === 1
            && in_array($match[1], ['uq_activities_name', 'activities.uq_activities_name'], true)) {
            return new BusinessException(ApiResponse::text('activities.name_duplicate'), 409, 'ACTIVITY_NAME_ALREADY_EXISTS');
        }

        // ACTIVITY_IN_USE will be mapped only when a later approved feature
        // introduces actual named restrictive dependencies. No generic 1451.
        return $exception;
    }
}
