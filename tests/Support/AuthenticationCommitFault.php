<?php

namespace Tests\Support;

use Illuminate\Database\Connection;
use PDO;
use PDOException;
use SensitiveParameter;

/**
 * Test-only PDO delegate. Commit real InnoDB writes, disconnect, then simulate
 * loss of the acknowledgement to the caller. Never used in production wiring.
 */
final class AuthenticationCommitFault
{
    public int $commits = 0;

    public function __construct(#[SensitiveParameter] private readonly PDO $pdo, private readonly Connection $connection) {}

    public function __call(string $name, #[SensitiveParameter] array $arguments): mixed
    {
        return $this->pdo->{$name}(...$arguments);
    }

    public function commit(): bool
    {
        $committed = $this->pdo->commit();
        if (! $committed) {
            return false;
        }
        $this->commits++;
        $this->connection->disconnect();
        $failure = new PDOException('Lost connection to MySQL server during query');
        $failure->errorInfo = ['HY000', 2013, 'Synthetic lost commit acknowledgement'];

        throw $failure;
    }
}
