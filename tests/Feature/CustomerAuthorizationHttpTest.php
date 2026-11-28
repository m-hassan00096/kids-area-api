<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Modules\Identity\Models\Permission;
use Modules\Identity\Models\Role;
use Modules\People\Models\Customer;

class CustomerAuthorizationHttpTest extends AuthenticationHttpTestCase
{
    public function test_custom_role_permissions_are_independent_and_denial_does_not_touch_customers(): void
    {
        $server = $this->server();
        $customer = Customer::query()->create(['name' => 'Synthetic target', 'mobile' => '00000000001']);
        $target = '/api/v1/customers/'.$customer->id;
        foreach (['show', 'store', 'update', 'none'] as $capability) {
            $role = Role::create(['name' => 'fixture-customer-authority-'.$capability, 'guard_name' => 'web', 'is_active' => true]);
            if ($capability !== 'none') {
                $role->givePermissionTo(Permission::findByName('people.customers.'.$capability, 'web'));
            }
            $actor = $this->user('Customer.Authority.'.ucfirst($capability), $role->name);
            $browser = $this->browser($server);
            $this->assertSame(200, $browser->login($actor->username)->status);
            foreach ([
                ['show', 'GET', $target, null, 200],
                ['store', 'POST', '/api/v1/customers', ['name' => 'Synthetic created'], 201],
                ['update', 'PUT', $target, ['name' => 'Synthetic edited'], 200],
            ] as [$required, $method, $path, $payload, $success]) {
                $response = $browser->request($method, $path, $payload);
                $allowed = $capability === $required;
                $this->assertSame($allowed ? $success : 403, $response->status, $capability.' '.$method);
                if (! $allowed) {
                    $this->assertSame('0', $response->header('X-Test-Customer-Reads'));
                    $this->assertSame('0', $response->header('X-Test-Customer-Writes'));
                    $this->assertArrayNotHasKey('data', $response->json());
                } elseif ($required === 'show') {
                    $this->assertSame('1', $response->header('X-Test-Customer-Reads'));
                    $this->assertSame('0', $response->header('X-Test-Customer-Writes'));
                } else {
                    $this->assertSame('1', $response->header('X-Test-Customer-Writes'));
                }
            }
        }
        $this->assertSame(2, Customer::count());
        $this->assertSame('Synthetic edited', $customer->fresh()->name);
        $this->assertNull($customer->fresh()->mobile);
    }

    public function test_live_revocation_disabled_staff_role_and_session_denial_precede_customer_reads(): void
    {
        $server = $this->server();
        $customer = Customer::query()->create(['name' => 'Synthetic protected']);
        $path = '/api/v1/customers/'.$customer->id;
        $permission = Permission::findByName('people.customers.show', 'web');
        foreach (['revoked', 'disabled-staff', 'disabled-role', 'session-missing'] as $scenario) {
            $role = Role::create(['name' => 'fixture-customer-'.$scenario, 'guard_name' => 'web', 'is_active' => true]);
            $role->givePermissionTo($permission);
            $actor = $this->user('Customer.Live.'.$scenario, $role->name);
            $browser = $this->browser($server);
            $this->assertSame(200, $browser->login($actor->username)->status);
            $this->assertSame(200, $browser->request('GET', $path)->status);

            match ($scenario) {
                'revoked' => $role->revokePermissionTo($permission),
                'disabled-staff' => $actor->update(['is_active' => false]),
                'disabled-role' => $role->update(['is_active' => false]),
                'session-missing' => DB::table('sessions')->where('id', $browser->sessionId())->delete(),
            };
            $response = $browser->request('GET', $path);
            $this->assertSame($scenario === 'session-missing' ? 401 : 403, $response->status, $scenario);
            $this->assertSame('0', $response->header('X-Test-Customer-Reads'));
            $this->assertSame('0', $response->header('X-Test-Customer-Writes'));
            $this->assertArrayNotHasKey('data', $response->json());
        }
        $guest = $this->browser($server);
        $response = $guest->request('GET', $path);
        $this->assertSame(401, $response->status);
        $this->assertSame('0', $response->header('X-Test-Customer-Reads'));
        $this->assertSame(1, Customer::count());
    }
}
