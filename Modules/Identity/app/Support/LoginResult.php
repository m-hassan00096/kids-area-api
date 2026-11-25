<?php

namespace Modules\Identity\Support;

final readonly class LoginResult
{
    public function __construct(public int $status, public ?int $retryAfter = null) {}
}
