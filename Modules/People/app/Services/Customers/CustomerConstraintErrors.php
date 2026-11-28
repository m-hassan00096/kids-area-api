<?php

namespace Modules\People\Services\Customers;

use App\Exceptions\BusinessException;
use App\Support\ApiResponse;
use Illuminate\Database\QueryException;
use SensitiveParameter;

final class CustomerConstraintErrors
{
    // The coordinating Action calls this only after a proven rollback.
    // Never reinterpret an unrelated SQL error or uncertain commit as a duplicate.
    public function translate(#[SensitiveParameter] QueryException $exception): QueryException|BusinessException
    {
        if ($exception->getConnectionName() === config('database.default')
            && ($exception->errorInfo[0] ?? null) === '23000'
            && (int) ($exception->errorInfo[1] ?? 0) === 1062
            && preg_match('/\A(?:insert\s+into|update)\s+\x60?customers\x60?\s/i', strtolower(ltrim($exception->getSql()))) === 1
            && preg_match("/ for key '([^']+)'\\z/", (string) ($exception->errorInfo[2] ?? ''), $match) === 1
            && in_array($match[1], ['uq_customers_mobile', 'customers.uq_customers_mobile'], true)) {
            return new BusinessException(ApiResponse::text('people.customers.mobile_duplicate'), 409, 'CUSTOMER_MOBILE_ALREADY_EXISTS');
        }

        return $exception;
    }
}
