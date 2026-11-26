<?php

namespace Modules\Identity\Services\Avatar;

use Illuminate\Support\Facades\Storage;
use RuntimeException;
use SensitiveParameter;
use Throwable;

class AvatarStorage
{
    public function stage(#[SensitiveParameter] NormalizedAvatar $image): string
    {
        if (! AvatarFormat::normalized($image->bytes, $image->extension)) {
            throw new RuntimeException('Invalid staged avatar.');
        }
        $key = $this->newKey($image->extension);
        $path = $this->path($key);
        // x+b never overwrites an existing file, including on key collision.
        $handle = @fopen($path, 'x+b');
        if ($handle === false) {
            throw new RuntimeException('Avatar exclusive create failed.');
        }
        $closed = false;
        try {
            if (! @chmod($path, 0600)) {
                throw new RuntimeException('Avatar permissions failed.');
            }
            $length = strlen($image->bytes);
            $offset = 0;
            while ($offset < $length) {
                $written = @fwrite($handle, substr($image->bytes, $offset));
                if ($written === false || $written === 0) {
                    throw new RuntimeException('Avatar write failed.');
                }
                $offset += $written;
            }
            if (! @fflush($handle) || ! @rewind($handle)) {
                throw new RuntimeException('Avatar flush failed.');
            }
            $actual = @stream_get_contents($handle, AvatarFormat::LIMIT + 1);
            if (! is_string($actual) || ! hash_equals($image->bytes, $actual)) {
                throw new RuntimeException('Avatar write verification failed.');
            }
            $closed = @fclose($handle);
            if (! $closed) {
                throw new RuntimeException('Avatar close failed.');
            }

            return $key;
        } catch (Throwable $exception) {
            if (! $closed && is_resource($handle)) {
                @fclose($handle);
            }
            // Only this attempt's exclusive create is eligible for cleanup.
            @unlink($path);
            throw $exception;
        }
    }

    public function read(string $key): NormalizedAvatar
    {
        $path = $this->path($key);
        clearstatcache(true, $path);
        $before = @lstat($path);
        if ($before === false || ($before['mode'] & 0170000) !== 0100000 || ($before['mode'] & 0077) !== 0) {
            throw new RuntimeException('Avatar unavailable.');
        }
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Avatar open failed.');
        }
        try {
            $stat = fstat($handle);
            if ($stat === false || $stat['ino'] !== $before['ino'] || $stat['dev'] !== $before['dev']
                || $stat['size'] < 1 || $stat['size'] > AvatarFormat::LIMIT) {
                throw new RuntimeException('Avatar file invalid.');
            }
            $bytes = @stream_get_contents($handle, AvatarFormat::LIMIT + 1);
        } finally {
            @fclose($handle);
        }
        $extension = AvatarFormat::extension($key);
        if (! is_string($bytes) || strlen($bytes) !== $stat['size'] || $extension === null
            || ! AvatarFormat::normalized($bytes, $extension)) {
            throw new RuntimeException('Avatar stored data invalid.');
        }

        return new NormalizedAvatar($extension, $bytes);
    }

    public function delete(string $key): void
    {
        $path = $this->path($key);
        clearstatcache(true, $path);
        $stat = @lstat($path);
        if ($stat === false && ! file_exists($path) && ! is_link($path)) {
            return;
        }
        if ($stat === false || ($stat['mode'] & 0170000) !== 0100000 || ! @unlink($path)) {
            throw new RuntimeException('Avatar cleanup failed.');
        }
    }

    protected function newKey(string $extension): string
    {
        return bin2hex(random_bytes(16)).'.'.$extension;
    }

    private function path(string $key): string
    {
        if (! AvatarFormat::validKey($key) || config('filesystems.disks.identity-avatars.driver') !== 'local') {
            throw new RuntimeException('Invalid avatar storage key or disk.');
        }
        $root = Storage::disk('identity-avatars')->path('');
        $root = rtrim($root, DIRECTORY_SEPARATOR);
        if (! is_dir($root) && ! @mkdir($root, 0700, true) && ! is_dir($root)) {
            throw new RuntimeException('Avatar root unavailable.');
        }
        clearstatcache(true, $root);
        $resolved = realpath($root);
        $public = realpath(public_path());
        $permissions = @fileperms($root);
        if ($resolved === false || $public === false || is_link($root) || $resolved === $public
            || str_starts_with($resolved.DIRECTORY_SEPARATOR, $public.DIRECTORY_SEPARATOR)
            || $permissions === false || ($permissions & 0077) !== 0) {
            throw new RuntimeException('Avatar root is not private.');
        }

        return $resolved.DIRECTORY_SEPARATOR.$key;
    }
}
