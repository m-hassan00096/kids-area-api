<?php

namespace Tests\Support;

use PDOException;
use PDOStatement;
use SensitiveParameter;

/**
 * Test-only native PDO statement subclass. Record actual server error 1213
 * without exposing SQL, bindings, or exception messages. No synthetic error.
 */
class UsersObservedStatement extends PDOStatement
{
    protected function __construct() {}

    public function execute(#[SensitiveParameter] ?array $params = null): bool
    {
        $sql = strtolower(str_replace(chr(96), '', $this->queryString));
        $measure = request()->headers->get('X-Identity-Test-Mode') === 'users-performance'
            && ! request()->attributes->has('_test_users_lock_acquired_ns')
            && str_contains($sql, 'from roles') && str_contains($sql, 'for update');
        $started = $measure ? hrtime(true) : null;
        try {
            $result = parent::execute($params);
            if ($result && $started !== null) {
                $acquired = hrtime(true);
                request()->attributes->set('_test_users_lock_acquired_ns', $acquired);
                request()->attributes->set('_test_users_lock_wait_ms', ($acquired - $started) / 1000000);
            }

            return $result;
        } catch (PDOException $exception) {
            $code = (int) ($exception->errorInfo[1] ?? 0);
            if (in_array(request()->headers->get('X-Identity-Test-Mode'), ['roles-action-deadlock', 'roles-action-duplicate', 'roles-action-timeout-restore'], true)
                && in_array($code, [1062, 1205, 1213], true)) {
                request()->attributes->set('_test_roles_mysql_code', $code);
                request()->attributes->set('_test_roles_mysql_count', 1 + (int) request()->attributes->get('_test_roles_mysql_count', 0));
            }
            if ($code === 1213) {
                request()->attributes->set('_test_users_deadlock_code', 1213);
                request()->attributes->set('_test_users_deadlock_count', 1 + (int) request()->attributes->get('_test_users_deadlock_count', 0));
            }

            throw $exception;
        }
    }
}
