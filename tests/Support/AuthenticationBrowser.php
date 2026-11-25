<?php

namespace Tests\Support;

use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;

final class AuthenticationBrowser
{
    public array $cookies = [];

    public int $timeMs;

    public function __construct(public AuthenticationHttpServer $server)
    {
        $this->timeMs = time() * 1000;
    }

    public function prepare(string $method, string $path, ?array $data = null, array $options = []): AuthenticationPendingRequest
    {
        $server = $options['server'] ?? $this->server;
        $headers = [
            'Accept' => 'application/json',
            'Origin' => $server->origin,
            'X-Identity-Test-Time' => (string) $this->timeMs,
        ];
        if (str_starts_with($server->origin, 'https:')) {
            $headers['X-Forwarded-Proto'] = 'https';
        }
        if ($this->cookies !== []) {
            $headers['Cookie'] = implode('; ', array_map(fn ($name, $value) => $name.'='.$value, array_keys($this->cookies), $this->cookies));
        }
        if (($options['csrf'] ?? 'valid') === 'valid' && isset($this->cookies['XSRF-TOKEN'])) {
            $headers['X-XSRF-TOKEN'] = rawurldecode($this->cookies['XSRF-TOKEN']);
        } elseif (($options['csrf'] ?? null) === 'bad') {
            $headers['X-XSRF-TOKEN'] = 'invalid-synthetic-csrf';
        }
        if ($data !== null || isset($options['raw'])) {
            $headers['Content-Type'] = 'application/json';
        }
        foreach ($options['headers'] ?? [] as $name => $value) {
            if ($value === null) {
                unset($headers[$name]);
            } else {
                $headers[$name] = $value;
            }
        }

        $handle = curl_init($server->url.$path);
        if ($handle === false) {
            throw new RuntimeException('Unable to initialize synthetic HTTP request.');
        }
        $pending = new AuthenticationPendingRequest($handle, $this);
        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => array_map(fn ($name, $value) => $name.': '.$value, array_keys($headers), $headers),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use ($pending): int {
                if (str_contains($line, ':')) {
                    [$name, $value] = explode(':', $line, 2);
                    $pending->headers[strtolower(trim($name))][] = trim($value);
                }

                return strlen($line);
            },
        ]);
        if ($data !== null || isset($options['raw'])) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $options['raw'] ?? json_encode($data, JSON_THROW_ON_ERROR));
        }

        return $pending;
    }

    public function request(string $method, string $path, ?array $data = null, array $options = []): AuthenticationHttpResponse
    {
        $pending = $this->prepare($method, $path, $data, $options);

        return $pending->finish(curl_exec($pending->handle));
    }

    public function handshake(array $options = []): AuthenticationHttpResponse
    {
        return $this->request('GET', '/sanctum/csrf-cookie', options: $options);
    }

    public function login(string $username, string $password = 'test-fixture-only-password', array $options = []): AuthenticationHttpResponse
    {
        return $this->request('POST', '/api/v1/auth/login', ['username' => $username, 'password' => $password], $options);
    }

    public function me(array $options = []): AuthenticationHttpResponse
    {
        return $this->request('GET', '/api/v1/auth/me', options: $options);
    }

    public function sessionId(): string
    {
        $cookie = $this->cookies[config('session.cookie')] ?? null;
        if ($cookie === null) {
            throw new RuntimeException('Synthetic browser has no session cookie.');
        }

        return CookieValuePrefix::remove(Crypt::decryptString(rawurldecode($cookie)));
    }
}
