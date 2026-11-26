<?php

namespace Modules\Identity\Support;

/**
 * Per-attempt state, including the conservative cross-store cleanup decision.
 * Never use the HTTP error alone to infer that a database write rolled back.
 */
final class UsersMutationOutcome
{
    public string $state = 'rolled_back';

    public bool $retainFiles = false;

    public function fileOutcome(): string
    {
        return $this->retainFiles ? 'unknown' : $this->state;
    }
}
