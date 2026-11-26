<?php

namespace App\Services\Select;

interface SelectProvider
{
    public function allows(array $permissions): bool;

    public function options(): array;
}
