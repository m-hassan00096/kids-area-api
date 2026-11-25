<?php

namespace App\Support;

final class BrowserOrigins
{
    public static function normalize(string $value, bool $strict = true): ?string
    {
        if (preg_match('/[\x00-\x20\x7f]/', $value) === 1) {
            return null;
        }

        $parts = parse_url($value);
        if ($parts === false || ! isset($parts['scheme'], $parts['host'])
            || ! in_array(strtolower($parts['scheme']), ['https', 'http'], true)
            || isset($parts['user']) || isset($parts['pass'])
            || str_contains($parts['host'], '*')
            || ($strict && (isset($parts['path']) || isset($parts['query']) || isset($parts['fragment'])))) {
            return null;
        }

        $scheme = strtolower($parts['scheme']);
        $port = $parts['port'] ?? null;
        if ($port === ($scheme === 'https' ? 443 : 80)) {
            $port = null;
        }

        return $scheme.'://'.strtolower($parts['host']).($port === null ? '' : ':'.$port);
    }

    public static function frontendOrigins(string $value): array
    {
        $origins = [];
        foreach (array_filter(array_map('trim', explode(',', $value)), 'strlen') as $entry) {
            $origin = self::normalize($entry);
            if ($origin === null) {
                // A bad entry invalidates the configured allowlist, not just itself.
                return [];
            }
            $origins[] = $origin;
        }

        return array_values(array_unique($origins));
    }

    public static function statefulHosts(array $origins): array
    {
        return array_values(array_unique(array_map(static function (string $origin): string {
            $port = parse_url($origin, PHP_URL_PORT);

            return parse_url($origin, PHP_URL_HOST).($port === null ? '' : ':'.$port);
        }, $origins)));
    }
}
