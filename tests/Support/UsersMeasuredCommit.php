<?php

namespace Tests\Support;

use PDO;
use SensitiveParameter;

/**
 * Observe real commit acknowledgement in the guarded HTTP test process.
 * No simulated error, SQL, bindings or credential data are emitted.
 */
final class UsersMeasuredCommit
{
    public function __construct(#[SensitiveParameter] private readonly PDO $pdo) {}

    public function __call(string $name, #[SensitiveParameter] array $arguments): mixed
    {
        return $this->pdo->{$name}(...$arguments);
    }

    public function commit(): bool
    {
        $result = $this->pdo->commit();
        $acquired = request()->attributes->get('_test_users_lock_acquired_ns');
        if ($result && is_int($acquired)) {
            request()->attributes->set('_test_users_lock_span_ms', (hrtime(true) - $acquired) / 1000000);
        }

        return $result;
    }
}
