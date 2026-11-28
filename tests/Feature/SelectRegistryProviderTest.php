<?php

namespace Tests\Feature;

use App\Services\Select\SelectProvider;
use App\Services\Select\SelectRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use Modules\Identity\Actions\SeedIdentityCatalogueAction;

class SelectRegistryProviderTest extends IdentityDatabaseTestCase
{
    public function test_registered_providers_return_empty_options_in_an_empty_catalogue(): void
    {
        $this->assertSame([
            ['label' => 'userFilterRoles', 'options' => []],
            ['label' => 'roles', 'options' => []],
            ['label' => 'permissions', 'options' => []],
            ['label' => 'activeActivities', 'options' => []],
            ['label' => 'activities', 'options' => []],
        ], app(SelectRegistry::class)->resolve('userFilterRoles,roles,permissions,activeActivities,activities', [
            'identity.users.view', 'identity.users.create', 'identity.roles.store', 'activities.index',
        ]));
    }

    public function test_permission_provider_exact_catalogue_and_missing_alias_integrity(): void
    {
        app(SeedIdentityCatalogueAction::class)->execute();
        DB::table('permissions')->where('name', 'identity.audit.view')->update(['name' => 'IDENTITY.AUDIT.VIEW']);
        $registry = app(SelectRegistry::class);
        $options = $registry->resolve('permissions', ['identity.roles.update'])[0]['options'];
        $this->assertCount(17, $options);
        $this->assertNotContains('IDENTITY.AUDIT.VIEW', array_column($options, 'value'));
        $this->assertSame($options, $registry->resolve('permissions', ['identity.roles.store'])[0]['options']);

        DB::table('permissions')->where('name', 'identity.users.view')->update(['alias_name' => ' ']);
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Missing stored permission label.');
        $registry->resolve('permissions', ['identity.roles.store']);
    }

    public function test_five_distinct_registered_keys_are_allowed_but_six_are_rejected_before_any_dispatch(): void
    {
        $registry = new SelectRegistry;
        $providers = [];
        for ($i = 1; $i <= 6; $i++) {
            $provider = new class implements SelectProvider
            {
                public int $authorizationCalls = 0;

                public int $optionCalls = 0;

                public function allows(array $permissions): bool
                {
                    $this->authorizationCalls++;

                    return true;
                }

                public function options(): array
                {
                    $this->optionCalls++;

                    return [];
                }
            };
            $providers['fixture'.$i] = $provider;
            $registry->register('fixture'.$i, $provider);
        }

        $keys = ['fixture5', 'fixture2', 'fixture1', 'fixture4', 'fixture3'];
        $this->assertSame(array_map(static fn ($key) => ['label' => $key, 'options' => []], $keys), $registry->resolve(implode(',', $keys), []));
        foreach ($providers as $key => $provider) {
            $this->assertSame((int) in_array($key, $keys, true), $provider->authorizationCalls);
            $this->assertSame((int) in_array($key, $keys, true), $provider->optionCalls);
            $provider->authorizationCalls = 0;
            $provider->optionCalls = 0;
        }

        try {
            $registry->resolve(implode(',', array_keys($providers)), []);
            $this->fail('Six registered keys must fail validation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('selects', $exception->errors());
        }
        foreach ($providers as $provider) {
            $this->assertSame(0, $provider->authorizationCalls);
            $this->assertSame(0, $provider->optionCalls);
        }
    }
}
