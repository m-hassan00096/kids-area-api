<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Identity\Models\Permission;
use Modules\Identity\Models\Role;
use Modules\People\Enums\CustomerStatus;
use Modules\People\Models\Customer;

class CustomerSelectHttpTest extends AuthenticationHttpTestCase
{
    public function test_complete_active_customer_options_with_exact_projection_and_shared_registry_limits(): void
    {
        $server = $this->server();
        $actor = $this->user('Customer.Select.Manager');
        $staff = $this->user('Customer.Select.Reception', 'reception');
        $admin = $this->browser($server);
        $reception = $this->browser($server);
        $this->assertSame(200, $admin->login($actor->username)->status);
        $this->assertSame(200, $reception->login($staff->username)->status);
        $path = '/api/v1/selects?selects=customers';
        $empty = $admin->request('GET', $path);
        $this->assertSame(200, $empty->status);
        $this->assertSame(['success' => true, 'message' => '', 'data' => [['label' => 'customers', 'options' => []]]], $empty->json());

        $z = Customer::query()->create(['name' => 'Zeta', 'mobile' => '00000000001']);
        $a = Customer::query()->create(['name' => 'Alpha']);
        $b = Customer::query()->create(['name' => 'Alpha', 'mobile' => '00000000002']);
        $arabic = Customer::query()->create(['name' => 'عميل تجريبي']);
        $inactive = DB::table('customers')->insertGetId(['name' => 'Aardvark', 'mobile' => '00000000003', 'is_active' => CustomerStatus::INACTIVE->value]);
        $rows = [];
        for ($i = 1; $i <= 205; $i++) {
            $rows[] = ['name' => 'Synthetic '.str_pad((string) $i, 4, '0', STR_PAD_LEFT), 'mobile' => null];
        }
        DB::table('customers')->insert($rows);
        $expected = DB::table('customers')->where('is_active', CustomerStatus::ACTIVE->value)->orderBy('name')->orderBy('id')
            ->get(['id', 'name', 'mobile'])->map(static fn (object $row): array => [
                'value' => (int) $row->id, 'label' => $row->mobile === null ? $row->name : $row->name.' - '.$row->mobile,
            ])->all();
        $this->assertCount(209, $expected);
        $this->assertSame([['value' => $a->id, 'label' => 'Alpha'], ['value' => $b->id, 'label' => 'Alpha - 00000000002']], array_slice($expected, 0, 2));
        $this->assertContains(['value' => $z->id, 'label' => 'Zeta - 00000000001'], $expected);
        $this->assertContains(['value' => $arabic->id, 'label' => 'عميل تجريبي'], $expected);
        $this->assertNotContains($inactive, array_column($expected, 'value'));

        foreach ([$admin, $reception] as $browser) {
            $response = $browser->request('GET', $path);
            $this->assertSame(200, $response->status);
            $this->assertSame(['success' => true, 'message' => '', 'data' => [['label' => 'customers', 'options' => $expected]]], $response->json());
            $this->assertSame('1', $response->header('X-Test-Select-Reads'));
            $this->assertSame('1', $response->header('X-Test-Customer-Reads'));
            $this->assertSame('0', $response->header('X-Test-Customer-Writes'));
            $this->assertSame('0', $response->header('X-Test-Select-Has-Limit'));
            $this->assertTrue(Str::isUuid($response->header('X-Request-ID')));
        }
        $five = ['customers', 'roles', 'permissions', 'activeActivities', 'userFilterRoles'];
        $response = $admin->request('GET', '/api/v1/selects?selects='.implode(',', $five));
        $this->assertSame(200, $response->status);
        $this->assertSame($five, array_column($response->json()['data'], 'label'));
        $this->assertSame($expected, $response->json()['data'][0]['options']);
        $this->assertCount(18, $response->json()['data'][2]['options']);
        $this->assertSame('5', $response->header('X-Test-Select-Reads'));
        $this->assertSame('1', $response->header('X-Test-Customer-Reads'));
        $this->assertSame('0', $response->header('X-Test-Select-Has-Limit'));

        foreach (['customers,roles,permissions,activeActivities,userFilterRoles,activities', 'Customers', 'customers,customers', 'customers,unknown'] as $keys) {
            $response = $admin->request('GET', '/api/v1/selects?selects='.$keys);
            $this->assertSame(422, $response->status);
            $this->assertSame('0', $response->header('X-Test-Select-Reads'));
            $this->assertSame('0', $response->header('X-Test-Customer-Reads'));
            $this->assertArrayNotHasKey('data', $response->json());
        }
        $this->assertSame(210, Customer::count());
    }

    public function test_customer_selection_is_independent_and_mixed_denial_precedes_all_reads(): void
    {
        $server = $this->server();
        foreach (['show', 'store', 'update', 'none'] as $capability) {
            $role = Role::create(['name' => 'fixture-customer-select-'.$capability, 'guard_name' => 'web', 'is_active' => true]);
            $role->givePermissionTo(Permission::findByName('activities.index', 'web'));
            if ($capability !== 'none') {
                $role->givePermissionTo(Permission::findByName('people.customers.'.$capability, 'web'));
            }
            $actor = $this->user('Customer.Select.'.ucfirst($capability), $role->name);
            $browser = $this->browser($server);
            $this->assertSame(200, $browser->login($actor->username)->status);
            foreach (['customers', 'customers,activities', 'activities,customers'] as $keys) {
                $response = $browser->request('GET', '/api/v1/selects?selects='.$keys);
                $allowed = $capability === 'show';
                $this->assertSame($allowed ? 200 : 403, $response->status, $capability.' '.$keys);
                $this->assertSame($allowed ? (string) count(explode(',', $keys)) : '0', $response->header('X-Test-Select-Reads'));
                $this->assertSame($allowed ? '1' : '0', $response->header('X-Test-Customer-Reads'));
                if (! $allowed) {
                    $this->assertArrayNotHasKey('data', $response->json());
                }
            }
            $this->assertSame(200, $browser->request('GET', '/api/v1/selects?selects=activities')->status);
        }
        $this->assertSame(0, Customer::count());
    }
}
