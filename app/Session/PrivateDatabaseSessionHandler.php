<?php

namespace App\Session;

use App\Support\SensitiveQueryScope;
use Illuminate\Session\DatabaseSessionHandler;
use SensitiveParameter;

// Keep Laravel's native database persistence, payload and expiry semantics.
// Only suppress the logging/events of the session operation itself.
final class PrivateDatabaseSessionHandler extends DatabaseSessionHandler
{
    public function read(#[SensitiveParameter] $sessionId): string|false
    {
        return SensitiveQueryScope::run($this->connection, fn () => parent::read($sessionId));
    }

    public function write(#[SensitiveParameter] $sessionId, #[SensitiveParameter] $data): bool
    {
        return SensitiveQueryScope::run($this->connection, fn () => parent::write($sessionId, $data));
    }

    public function destroy(#[SensitiveParameter] $sessionId): bool
    {
        return SensitiveQueryScope::run($this->connection, fn () => parent::destroy($sessionId));
    }

    public function gc($lifetime): int
    {
        return SensitiveQueryScope::run($this->connection, fn () => parent::gc($lifetime));
    }
}
