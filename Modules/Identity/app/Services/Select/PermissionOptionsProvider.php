<?php

namespace Modules\Identity\Services\Select;

use App\Exceptions\BusinessException;
use App\Services\Select\SelectProvider;
use App\Support\ApiResponse;
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
        $limit = config('selects.max_options');
        if (! is_int($limit) || $limit < 1 || $limit > 200) {
            throw new LogicException('Invalid Select option cap.');
        }

        $permissions = PermissionCatalogue::constrain(DB::table('permissions as p')->useWritePdo())
            ->orderBy('p.name')->limit($limit + 1)->get(['p.name', 'p.alias_name']);
        if ($permissions->count() > $limit) {
            throw new BusinessException(ApiResponse::text('identity.select_options_limit'), 409, 'SELECT_OPTIONS_LIMIT');
        }

        return $permissions->map(static function (object $permission): array {
            if (! is_string($permission->alias_name) || trim($permission->alias_name) === '') {
                throw new LogicException('Missing stored permission label.');
            }

            return ['value' => $permission->name, 'label' => $permission->alias_name];
        })->all();
    }
}
