<?php

namespace Tests\Support;

use Closure;
use PDO;
use SensitiveParameter;

/** Test-only PDO delegate: pause after native grant synchronization, at the real transaction boundary. */
final class RolesCacheBarrier
{
    private bool $held = false;

    public function __construct(
        #[SensitiveParameter] private readonly PDO $pdo,
        private readonly Closure $pause,
        private readonly bool $commitFirst,
    ) {}

    public function __call(string $name, #[SensitiveParameter] array $arguments): mixed
    {
        return $this->pdo->{$name}(...$arguments);
    }

    public function commit(): bool
    {
        if ($this->commitFirst && ! $this->held) {
            $this->held = true;
            ($this->pause)();
        }

        return $this->pdo->commit();
    }

    public function rollBack(): bool
    {
        if (! $this->commitFirst && ! $this->held) {
            $this->held = true;
            ($this->pause)();
        }

        return $this->pdo->rollBack();
    }
}
