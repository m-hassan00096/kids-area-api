<?php

namespace Modules\Identity\Support;

use Illuminate\Database\Query\Builder;
use LogicException;
use Modules\Activities\Support\ActivityPermissions;
use Modules\People\Support\CustomerPermissions;

final class PermissionCatalogue
{
    public static function definitions(): array
    {
        $definitions = [];
        foreach ([IdentityPermissions::CATALOGUE, ActivityPermissions::CATALOGUE, CustomerPermissions::CATALOGUE] as $catalogue) {
            if (array_intersect_key($definitions, $catalogue) !== []) {
                throw new LogicException('Duplicate permission catalogue key.');
            }
            $definitions += $catalogue;
        }

        return $definitions;
    }

    public static function keys(): array
    {
        return array_keys(self::definitions());
    }

    // The alias is a fixed internal SQL identifier supplied only by our queries.
    // Both indexed candidates and exact binary membership are required.
    public static function constrain(Builder $query): Builder
    {
        $keys = self::keys();
        $placeholders = implode(', ', array_fill(0, count($keys), '?'));

        return $query->where('p.guard_name', 'web')->whereRaw('BINARY p.guard_name = ?', ['web'])
            ->whereIn('p.name', $keys)->whereRaw('BINARY p.name IN ('.$placeholders.')', $keys);
    }
}
