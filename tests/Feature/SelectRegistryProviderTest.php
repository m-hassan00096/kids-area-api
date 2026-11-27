<?php

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Services\Select\SelectRegistry;
use Illuminate\Support\Facades\DB;
use Modules\Identity\Actions\SeedIdentityCatalogueAction;

class SelectRegistryProviderTest extends IdentityDatabaseTestCase
{
    public function test_registered_providers_return_empty_options_in_an_empty_catalogue(): void
    {
        $this->assertSame([
            ['label' => 'userFilterRoles', 'options' => []],
            ['label' => 'roles', 'options' => []],
            ['label' => 'permissions', 'options' => []],
        ], app(SelectRegistry::class)->resolve('userFilterRoles,roles,permissions', ['identity.users.view', 'identity.users.create', 'identity.roles.store']));
    }

    public function test_permission_provider_exact_name_and_bounded_overflow(): void
    {
        app(SeedIdentityCatalogueAction::class)->execute();
        DB::table('permissions')->where('name', 'identity.audit.view')->update(['name' => 'IDENTITY.AUDIT.VIEW']);
        $registry = app(SelectRegistry::class);
        $options = $registry->resolve('permissions', ['identity.roles.update'])[0]['options'];
        $this->assertCount(14, $options);
        $this->assertNotContains('IDENTITY.AUDIT.VIEW', array_column($options, 'value'));
        config()->set('selects.max_options', 14);
        $this->assertCount(14, $registry->resolve('permissions', ['identity.roles.store'])[0]['options']);
        config()->set('selects.max_options', 13);
        try {
            $registry->resolve('permissions', ['identity.roles.store']);
            $this->fail('Expected bounded permission options conflict.');
        } catch (BusinessException $exception) {
            $this->assertSame(409, $exception->httpStatus);
            $this->assertSame('SELECT_OPTIONS_LIMIT', $exception->errorCode);
        }
    }
}
