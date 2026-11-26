<?php

namespace Modules\Identity\Support;

use Illuminate\Database\Query\Builder;

final class PermissionCatalogue
{
    // The alias is a fixed internal SQL identifier supplied only by our queries.
    // Both indexed candidates and exact binary membership are required.
    public static function constrain(Builder $query): Builder
    {
        $keys = IdentityPermissions::keys();
        $placeholders = implode(', ', array_fill(0, count($keys), '?'));

        return $query->where('p.guard_name', 'web')->whereRaw('BINARY p.guard_name = ?', ['web'])
            ->whereIn('p.name', $keys)->whereRaw('BINARY p.name IN ('.$placeholders.')', $keys);
    }
}
