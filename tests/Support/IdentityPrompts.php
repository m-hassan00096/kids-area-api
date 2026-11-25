<?php

namespace Tests\Support;

use Modules\Identity\Console\BootstrapManagerCommand;
use RuntimeException;

/** Synthetic prompts for exercising the actual command without a real staff password. */
class IdentityPrompts extends BootstrapManagerCommand
{
    public array $answers = [];

    public array $fallbacks = [];

    public bool $terminal = true;

    public bool $failSecret = false;

    public function ask($question, $default = null)
    {
        return array_shift($this->answers);
    }

    public function secret($question, $fallback = true)
    {
        $this->fallbacks[] = $fallback;

        if ($this->failSecret) {
            throw new RuntimeException('Test-only hidden prompt failure');
        }

        return array_shift($this->answers);
    }

    protected function hasTerminal(): bool
    {
        return $this->terminal;
    }
}
