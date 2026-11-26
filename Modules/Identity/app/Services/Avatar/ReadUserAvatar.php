<?php

namespace Modules\Identity\Services\Avatar;

use Modules\Identity\Models\User;
use RuntimeException;

final readonly class ReadUserAvatar
{
    public function __construct(private AvatarStorage $storage) {}

    public function execute(User $authorizedTarget): NormalizedAvatar
    {
        $key = $authorizedTarget->avatar_key;
        abort_if($key === null, 404);
        try {
            return $this->storage->read($key);
        } catch (RuntimeException $exception) {
            // An immutable old file may have been unlinked after replacement.
            // Re-read authoritative state once; never follow a caller path.
            $current = User::query()->useWritePdo()->whereKey($authorizedTarget->id)->first(['id', 'avatar_key']);
            abort_if($current === null || $current->avatar_key === null, 404);
            if ($current->avatar_key === $key) {
                throw $exception;
            }

            return $this->storage->read($current->avatar_key);
        }
    }
}
