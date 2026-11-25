<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use RuntimeException;

final class TrustedIdentityProxies extends TrustProxies
{
    protected function setTrustedProxyIpAddresses(Request $request)
    {
        $proxies = config('identity-browser.trusted_proxies', []);
        foreach ($proxies as $proxy) {
            // No wildcard, forwarded-host trust, or trust-all CIDR.
            $parts = explode('/', $proxy);
            if (filter_var($parts[0], FILTER_VALIDATE_IP) === false
                || count($parts) > 2
                || (isset($parts[1]) && (! ctype_digit($parts[1]) || (int) $parts[1] < 1
                    || (int) $parts[1] > (str_contains($parts[0], ':') ? 128 : 32)))) {
                throw new RuntimeException('Invalid trusted proxy configuration.');
            }
        }

        $request::setTrustedProxies($proxies, Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO | Request::HEADER_X_FORWARDED_PORT);
    }
}
