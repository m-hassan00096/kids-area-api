<?php

namespace Modules\Identity\Services\Avatar;

final readonly class NormalizedAvatar
{
    public function __construct(public string $extension, public string $bytes) {}
}
