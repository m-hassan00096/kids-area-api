<?php

namespace Modules\Identity\Models;

use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
