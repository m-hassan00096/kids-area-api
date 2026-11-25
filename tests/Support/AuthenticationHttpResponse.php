<?php

namespace Tests\Support;

final readonly class AuthenticationHttpResponse
{
    public function __construct(public int $status, public string $body, public array $headers) {}

    public function json(): array
    {
        return json_decode($this->body, true, 512, JSON_THROW_ON_ERROR);
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)][0] ?? null;
    }
}
