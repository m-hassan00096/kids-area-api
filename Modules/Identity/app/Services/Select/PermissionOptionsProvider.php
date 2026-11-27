<?php

namespace Modules\Identity\Services\Select;

use App\Services\Select\SelectProvider;
use Illuminate\Support\Facades\DB;
use LogicException;
use Modules\Identity\Support\PermissionCatalogue;

final class PermissionOptionsProvider implements SelectProvider
{
    public function allows(array $permissions): bool
    {
        return in_array('identity.roles.store', $permissions, true)
            || in_array('identity.roles.update', $permissions, true);
    }

    public function options(): array
    {

        $permissions = PermissionCatalogue::constrain(DB::table('permissions as p')->useWritePdo())
            ->orderBy('p.name')->get(['p.name', 'p.alias_name']);

        return $permissions->map(static function (object $permission): array {
            if (! is_string($permission->alias_name) || trim($permission->alias_name) === '') {
                throw new LogicException('Missing stored permission label.');
            }

            return ['value' => $permission->name, 'label' => $permission->alias_name];
        })->all();
    }
}
