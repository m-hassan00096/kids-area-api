<?php

namespace Modules\Identity\Services\Avatar;

// Loaded only by the guarded cli-server test router for a single request.
// Exercise the real AvatarStorage exclusive-create/write/close cleanup path.
function fwrite($handle, #[\SensitiveParameter] string $data): int|false
{
    if (request()->headers->get('X-Identity-Test-Mode') === 'fault-avatar-write') {
        \fwrite($handle, substr($data, 0, 1));

        return false;
    }

    return \fwrite($handle, $data);
}

function fclose($handle): bool
{
    $closed = \fclose($handle);
    if (request()->headers->get('X-Identity-Test-Mode') === 'fault-avatar-close') {
        return false;
    }

    return $closed;
}
