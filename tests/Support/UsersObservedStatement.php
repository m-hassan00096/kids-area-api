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
        try {
            return parent::execute($params);
        } catch (PDOException $exception) {
            if ((int) ($exception->errorInfo[1] ?? 0) === 1213) {
                request()->attributes->set('_test_users_deadlock_code', 1213);
                request()->attributes->set('_test_users_deadlock_count', 1 + (int) request()->attributes->get('_test_users_deadlock_count', 0));
            }

            throw $exception;
        }
    }
}
