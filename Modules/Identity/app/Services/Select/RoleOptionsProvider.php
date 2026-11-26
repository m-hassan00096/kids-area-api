<?php

namespace Modules\Identity\Services\Select;

use App\Exceptions\BusinessException;
use App\Services\Select\SelectProvider;
use App\Support\ApiResponse;
use LogicException;
use Modules\Identity\Models\Role;

final readonly class RoleOptionsProvider implements SelectProvider
{
    public function __construct(private bool $assignment) {}

    public function allows(array $permissions): bool
    {
        if ($this->assignment) {
            return in_array('identity.users.create', $permissions, true)
                || in_array('identity.users.update', $permissions, true);
        }

        return in_array('identity.users.view', $permissions, true);
    }

    public function options(): array
    {
        $limit = config('selects.max_options');
        if (! is_int($limit) || $limit < 1 || $limit > 200) {
            throw new LogicException('Invalid Select option cap.');
        }

        $query = Role::query()->useWritePdo()
            ->where('guard_name', 'web')->whereRaw('BINARY guard_name = ?', ['web']);
        if ($this->assignment) {
            $query->where('is_active', true);
        }
        $roles = $query->orderBy('id')->limit($limit + 1)->get(['id', 'name']);
        if ($roles->count() > $limit) {
            throw new BusinessException(ApiResponse::text('identity.select_options_limit'), 409, 'SELECT_OPTIONS_LIMIT');
        }

        return $roles->map(static fn (Role $role): array => ['value' => (int) $role->id, 'label' => $role->name])->all();
    }
}
