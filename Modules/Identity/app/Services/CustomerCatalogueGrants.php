<?php

namespace Modules\Identity\Services;

use App\Exceptions\BusinessException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;
use Modules\Identity\Models\Permission;
use Modules\Identity\Models\Role;
use Modules\Identity\Support\PermissionCatalogue;
use Modules\People\Support\CustomerPermissions;

final class CustomerCatalogueGrants
{
    public const FEATURE_KEY = 'people.customers.v1';

    public function __construct(private readonly ActivityCatalogueGrants $activityGrants) {}

    // Caller must hold the permanent manager mutex in its own transaction.
    public function completed(): bool
    {
        $this->requireTransaction();
        $marker = DB::table('identity_feature_provisions')->where('feature_key', self::FEATURE_KEY)
            ->lockForUpdate()->first();
        if ($marker !== null && $marker->feature_key !== self::FEATURE_KEY) {
            $this->invalid();
        }

        return $marker !== null;
    }

    /** The same grant/marker boundary serves the fresh seed and explicit upgrade. */
    public function complete(Role $manager, Role $reception, Collection $definitions, bool $fresh): bool
    {
        $this->requireTransaction();
        if ($manager->name !== 'manager' || $manager->guard_name !== 'web' || ! $manager->is_active
            || $reception->name !== 'reception' || $reception->guard_name !== 'web' || ! $reception->is_active
            || $manager->id === $reception->id) {
            $this->invalid();
        }

        $keys = PermissionCatalogue::keys();
        if ($definitions->count() !== count($keys)
            || $definitions->contains(fn (Permission $permission) => $permission->guard_name !== 'web'
                || ! in_array($permission->name, $keys, true)
                || ! is_string($permission->alias_name) || trim($permission->alias_name) === '')
            || $definitions->pluck('name')->uniqueStrict()->count() !== count($keys)) {
            $this->invalid();
        }
        $byName = $definitions->keyBy('name');
        $validIds = $definitions->pluck('id')->map(fn ($id) => (int) $id)->all();
        $stored = DB::table('role_has_permissions')->whereIn('role_id', [$manager->id, $reception->id])
            ->orderBy('role_id')->orderBy('permission_id')->lockForUpdate()->get();
        if ($stored->contains(fn ($grant) => ! in_array((int) $grant->permission_id, $validIds, true))) {
            $this->invalid();
        }

        if (! $this->activityGrants->completed()) {
            $this->invalid();
        }

        if ($this->completed()) {
            if ($fresh) {
                // A completed marker plus a supposedly new catalogue is inconsistent.
                $this->invalid();
            }

            return false;
        }

        // Fresh Activity provisioning has already granted the full manager
        // catalogue and its own Reception defaults within this transaction.
        // An upgrade adds only the three Customer permissions to each role.
        $defaults = [
            [$manager, CustomerPermissions::keys()],
            [$reception, CustomerPermissions::keys()],
        ];
        foreach ($defaults as [$role, $required]) {
            $permissions = collect($required)->map(fn (string $name) => $byName->get($name));
            $existing = $stored->where('role_id', $role->id)->pluck('permission_id')->map(fn ($id) => (int) $id)->all();
            $missing = $permissions->reject(fn (Permission $permission) => in_array((int) $permission->id, $existing, true));
            if ($missing->isNotEmpty()) {
                // Use native models, never replace an existing role's custom subset.
                $role->givePermissionTo($missing->values());
            }
            $expected = array_values(array_unique([...$existing, ...$permissions->pluck('id')->map(fn ($id) => (int) $id)->all()]));
            $actual = DB::table('role_has_permissions')->where('role_id', $role->id)
                ->orderBy('permission_id')->lockForUpdate()->pluck('permission_id')->map(fn ($id) => (int) $id)->all();
            sort($expected, SORT_NUMERIC);
            if ($actual !== $expected) {
                $this->invalid();
            }
        }

        DB::table('identity_feature_provisions')->insert([
            'feature_key' => self::FEATURE_KEY,
            'completed_at' => now()->utc()->format('Y-m-d H:i:s'),
        ]);

        return true;
    }

    private function requireTransaction(): void
    {
        if (DB::transactionLevel() !== 1) {
            throw new LogicException('Catalogue provisioning requires the caller-owned Identity transaction.');
        }
    }

    private function invalid(): never
    {
        throw new BusinessException(__('identity.seed_prerequisites'), errorCode: 'IDENTITY_SEED_REQUIRED');
    }
}
