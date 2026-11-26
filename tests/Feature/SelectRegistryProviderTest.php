<?php

namespace Tests\Feature;

use App\Services\Select\SelectRegistry;

class SelectRegistryProviderTest extends IdentityDatabaseTestCase
{
    public function test_registered_providers_return_empty_options_in_an_empty_catalogue(): void
    {
        $this->assertSame([
            ['label' => 'userFilterRoles', 'options' => []],
            ['label' => 'roles', 'options' => []],
        ], app(SelectRegistry::class)->resolve('userFilterRoles,roles', ['identity.users.view', 'identity.users.create']));
    }
}
