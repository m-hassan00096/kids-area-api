<?php

namespace Tests\Support;

use CurlHandle;
use RuntimeException;

final class AuthenticationPendingRequest
{
    public array $headers = [];

    public function __construct(public readonly CurlHandle $handle, private readonly AuthenticationBrowser $browser) {}

    public function finish(string|false $body): AuthenticationHttpResponse
    {
        if ($body === false || curl_errno($this->handle) !== 0) {
            throw new RuntimeException('Synthetic HTTP request failed to complete.');
        }
        foreach ($this->headers['set-cookie'] ?? [] as $header) {
            $pair = explode(';', $header, 2)[0];
            [$name, $value] = explode('=', $pair, 2);
            if ($value === '' || str_contains(strtolower($header), 'max-age=0')) {
                unset($this->browser->cookies[$name]);
            } else {
                $this->browser->cookies[$name] = $value;
            }
        }

        return new AuthenticationHttpResponse((int) curl_getinfo($this->handle, CURLINFO_RESPONSE_CODE), $body, $this->headers);
    }
}
