<?php

namespace Modules\Identity\Services\Avatar;

final class AvatarFormat
{
    public const LIMIT = 2097152;

    public static function extension(string $name): ?string
    {
        return match (strtolower(pathinfo($name, PATHINFO_EXTENSION))) {
            'jpeg', 'jpg' => 'jpg',
            'png' => 'png',
            'webp' => 'webp',
            default => null,
        };
    }

    public static function mime(string $extension): ?string
    {
        return match ($extension) {
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => null,
        };
    }

    public static function validKey(string $key): bool
    {
        return preg_match('/\A[a-f0-9]{32}\.(?:jpg|png|webp)\z/', $key) === 1;
    }

    public static function structure(string $bytes, string $extension): bool
    {
        if ($bytes === '' || strlen($bytes) > self::LIMIT) {
            return false;
        }

        return match ($extension) {
            'jpg' => self::jpeg($bytes),
            'png' => self::png($bytes),
            'webp' => self::webp($bytes),
            default => false,
        };
    }

    public static function normalized(string $bytes, string $extension): bool
    {
        if (! self::structure($bytes, $extension) || (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) !== self::mime($extension)) {
            return false;
        }
        $info = @getimagesizefromstring($bytes);

        return is_array($info) && ($info['mime'] ?? null) === self::mime($extension)
            && $info[0] > 0 && $info[1] > 0 && $info[0] <= 512 && $info[1] <= 512;
    }

    // Walk actual length-delimited marker segments and entropy scans.
    // An EOI-like byte pair inside APP/COM data is not a terminating marker.
    private static function jpeg(string $bytes): bool
    {
        $size = strlen($bytes);
        if ($size < 4 || substr($bytes, 0, 2) !== "\xff\xd8") {
            return false;
        }
        $position = 2;
        $scan = false;
        $scans = 0;
        $frame = false;
        $restart = 0;
        $interval = 0;
        $frames = [0xc0, 0xc1, 0xc2, 0xc3, 0xc5, 0xc6, 0xc7, 0xc9, 0xca, 0xcb, 0xcd, 0xce, 0xcf];
        $segments = array_merge($frames, [0xc4, 0xcc, 0xda, 0xdb, 0xdd, 0xfe], range(0xe0, 0xef));
        while ($position < $size) {
            if ($scan) {
                $next = strpos($bytes, "\xff", $position);
                if ($next === false) {
                    return false;
                }
                $position = $next;
            } elseif (ord($bytes[$position]) !== 255) {
                return false;
            }
            $position++;
            while ($position < $size && ord($bytes[$position]) === 255) {
                $position++;
            }
            if ($position === $size) {
                return false;
            }
            $marker = ord($bytes[$position++]);
            if ($scan && $marker === 0) {
                continue;
            }
            if ($scan && $marker >= 0xd0 && $marker <= 0xd7) {
                if ($interval === 0 || $marker !== 0xd0 + $restart) {
                    return false;
                }
                $restart = ($restart + 1) % 8;

                continue;
            }
            $scan = false;
            if ($marker === 0xd9) {
                return $frame && $scans > 0 && $position === $size;
            }
            if (! in_array($marker, $segments, true) || $position + 2 > $size) {
                return false;
            }
            $length = unpack('n', substr($bytes, $position, 2))[1];
            if ($length < 2 || $length > $size - $position) {
                return false;
            }
            if (in_array($marker, $frames, true)) {
                if ($frame || $length < 11 || $length !== 8 + 3 * ord($bytes[$position + 7])) {
                    return false;
                }
                $frame = true;
            }
            if ($marker === 0xda) {
                if (! $frame || $length < 8 || $length !== 6 + 2 * ord($bytes[$position + 2])) {
                    return false;
                }
                $scan = true;
                $scans++;
                $restart = 0;
            }
            if ($marker === 0xdd) {
                if ($length !== 4) {
                    return false;
                }
                $interval = unpack('n', substr($bytes, $position + 2, 2))[1];
            }
            $position += $length;
        }

        return false;
    }

    private static function png(string $bytes): bool
    {
        if (! str_starts_with($bytes, "\x89PNG\r\n\x1a\n")) {
            return false;
        }
        $size = strlen($bytes);
        $position = 8;
        $first = true;
        $idat = false;
        $endedData = false;
        $palette = false;
        while ($position + 12 <= $size) {
            $length = unpack('N', substr($bytes, $position, 4))[1];
            $tag = substr($bytes, $position + 4, 4);
            if ($length > $size - $position - 12 || preg_match('/\A[A-Za-z]{2}[A-Z][A-Za-z]\z/', $tag) !== 1) {
                return false;
            }
            $payload = substr($bytes, $position + 8, $length);
            if (! hash_equals(pack('N', crc32($tag.$payload)), substr($bytes, $position + 8 + $length, 4))) {
                return false;
            }
            if (($first && ($tag !== 'IHDR' || $length !== 13)) || (! $first && $tag === 'IHDR')) {
                return false;
            }
            $first = false;
            if (in_array($tag, ['acTL', 'fcTL', 'fdAT'], true)
                || (ctype_upper($tag[0]) && ! in_array($tag, ['IHDR', 'PLTE', 'IDAT', 'IEND'], true))) {
                return false;
            }
            if ($tag === 'PLTE') {
                if ($palette || $idat || $length < 3 || $length > 768 || $length % 3 !== 0) {
                    return false;
                }
                $palette = true;
            }
            if ($idat && $tag !== 'IDAT') {
                $endedData = true;
            }
            if ($tag === 'IDAT') {
                if ($endedData) {
                    return false;
                }
                $idat = true;
            }
            $position += 12 + $length;
            if ($tag === 'IEND') {
                return $length === 0 && $idat && $position === $size;
            }
        }

        return false;
    }

    private static function webp(string $bytes): bool
    {
        $size = strlen($bytes);
        if ($size < 12 || substr($bytes, 0, 4) !== 'RIFF' || substr($bytes, 8, 4) !== 'WEBP'
            || unpack('V', substr($bytes, 4, 4))[1] !== $size - 8) {
            return false;
        }
        $position = 12;
        $image = false;
        $extended = false;
        $alpha = false;
        while ($position + 8 <= $size) {
            $tag = substr($bytes, $position, 4);
            $length = unpack('V', substr($bytes, $position + 4, 4))[1];
            if ($length > $size - $position - 8 || in_array($tag, ['ANIM', 'ANMF'], true)) {
                return false;
            }
            if ($tag === 'VP8X') {
                if ($extended || $position !== 12 || $length !== 10 || (ord($bytes[$position + 8]) & 0xc3)
                    || substr($bytes, $position + 9, 3) !== "\x00\x00\x00") {
                    return false;
                }
                $extended = true;
            }
            if ($tag === 'ALPH') {
                if (! $extended || $alpha || $image || $length < 1) {
                    return false;
                }
                $alpha = true;
            }
            if (in_array($tag, ['VP8 ', 'VP8L'], true)) {
                if ($image || $length < 5 || ($tag === 'VP8L' && $alpha)) {
                    return false;
                }
                $image = true;
            }
            if ($length % 2 && ($position + 8 + $length >= $size || ord($bytes[$position + 8 + $length]) !== 0)) {
                return false;
            }
            $position += 8 + $length + ($length % 2);
        }

        return $position === $size && $image;
    }
}
