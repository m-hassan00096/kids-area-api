<?php

namespace Modules\Identity\Services\Select;

use App\Services\Select\SelectProvider;
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
        $query = Role::query()->useWritePdo()
            ->where('guard_name', 'web')->whereRaw('BINARY guard_name = ?', ['web']);
        if ($this->assignment) {
            $query->where('is_active', true);
        }
        $roles = $query->orderBy('id')->get(['id', 'name']);

        return $roles->map(static fn (Role $role): array => ['value' => (int) $role->id, 'label' => $role->name])->all();
    }
}
