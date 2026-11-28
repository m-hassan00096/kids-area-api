<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Modules\Identity\Models\Role;
use Modules\People\Enums\CustomerStatus;
use Modules\People\Models\Customer;
use Tests\Support\AuthenticationHttpResponse;
use Tests\Support\AuthenticationRaceHarness;
use Tests\Support\UsersRaceFixtures;

class CustomerRaceHttpTest extends AuthenticationHttpTestCase
{
    use AuthenticationRaceHarness, UsersRaceFixtures;

    public function test_concurrent_duplicate_creates_and_competing_updates_have_one_mysql_winner(): void
    {
        $actor = $this->user('Customer.Race.Manager');
        $staff = $this->user('Customer.Race.Reception', 'reception');
        $a = $this->browser($this->server());
        $b = $this->browser($this->server());
        $this->assertSame(200, $a->login($actor->username)->status);
        $this->assertSame(200, $b->login($staff->username)->status);

        [$first, $second] = $this->race(
            $a->prepare('POST', '/api/v1/customers', ['name' => 'Synthetic first', 'mobile' => '00000000001'], $this->raceOptions('customer-hold-insert', 'first')),
            $b->prepare('POST', '/api/v1/customers', ['name' => 'Synthetic second', 'mobile' => '00000000001'], $this->raceOptions('customer-observe-mysql', 'second')),
            'manager',
        );
        $this->assertCommitted($first, 201);
        $this->assertDuplicate($second);
        $winner = Customer::query()->where('mobile', '00000000001')->sole();
        $this->assertSame(['id' => $winner->id, 'name' => 'Synthetic first', 'mobile' => '00000000001'], $first->json()['data']);
        $this->assertSame(CustomerStatus::ACTIVE, $winner->is_active);
        $this->assertSame(1, Customer::count());

        $target = Customer::query()->create(['name' => 'Synthetic target', 'mobile' => '00000000002'])->fresh();
        $path = '/api/v1/customers/'.$target->id;
        $before = $target->getRawOriginal();
        [$first, $second] = $this->race(
            $a->prepare('POST', '/api/v1/customers', ['name' => 'Synthetic create wins', 'mobile' => '00000000003'], $this->raceOptions('customer-hold-insert', 'first')),
            $b->prepare('PUT', $path, ['name' => 'Synthetic losing update', 'mobile' => '00000000003'], $this->raceOptions('customer-observe-mysql', 'second')),
            'manager',
        );
        $this->assertCommitted($first, 201);
        $this->assertDuplicate($second);
        $this->assertSame($before, $target->fresh()->getRawOriginal());
        $this->assertSame('Synthetic create wins', Customer::query()->where('mobile', '00000000003')->sole()->name);

        // An inactive retained row still owns its unique mobile. A normal
        // authorized update may change its mobile but cannot reactivate it.
        DB::table('customers')->where('id', $target->id)->update(['is_active' => CustomerStatus::INACTIVE->value]);
        [$first, $second] = $this->race(
            $a->prepare('PUT', $path, ['name' => 'Synthetic update wins', 'mobile' => '00000000004'], $this->raceOptions('customer-hold-update', 'first')),
            $b->prepare('POST', '/api/v1/customers', ['name' => 'Synthetic losing create', 'mobile' => '00000000004'], $this->raceOptions('customer-observe-mysql', 'second')),
            'manager',
        );
        $this->assertCommitted($first, 200);
        $this->assertDuplicate($second);
        $this->assertSame('Synthetic update wins', $target->fresh()->name);
        $this->assertSame(CustomerStatus::INACTIVE, $target->fresh()->is_active);
        $this->assertSame($target->id, Customer::query()->where('mobile', '00000000004')->sole()->id);
        $this->assertSame(3, Customer::count());

        $other = Customer::query()->create(['name' => 'Synthetic other', 'mobile' => '00000000005'])->fresh();
        $otherBefore = $other->getRawOriginal();
        [$first, $second] = $this->race(
            $a->prepare('PUT', $path, ['name' => 'Synthetic shared winner', 'mobile' => '00000000006'], $this->raceOptions('customer-hold-update', 'first')),
            $b->prepare('PUT', '/api/v1/customers/'.$other->id, ['name' => 'Synthetic shared loser', 'mobile' => '00000000006'], $this->raceOptions('customer-observe-mysql', 'second')),
            'manager',
        );
        $this->assertCommitted($first, 200);
        $this->assertDuplicate($second);
        $this->assertSame($otherBefore, $other->fresh()->getRawOriginal());
        $this->assertSame($target->id, Customer::query()->where('mobile', '00000000006')->sole()->id);
        $this->assertSame(CustomerStatus::INACTIVE, $target->fresh()->is_active);
        $this->assertSame(4, Customer::count());
    }

    public function test_serial_full_updates_on_one_row_preserve_status_and_last_authorized_commit_wins(): void
    {
        $actor = $this->user('Customer.Serial.Manager');
        $a = $this->browser($this->server());
        $b = $this->browser($this->server());
        $this->assertSame(200, $a->login($actor->username)->status);
        $this->assertSame(200, $b->login($actor->username)->status);
        $target = Customer::query()->create(['name' => 'Synthetic original', 'mobile' => '00000000001']);
        DB::table('customers')->where('id', $target->id)->update(['is_active' => CustomerStatus::INACTIVE->value]);
        $path = '/api/v1/customers/'.$target->id;

        [$first, $second] = $this->race(
            $a->prepare('PUT', $path, ['name' => 'Synthetic first update', 'mobile' => '00000000002'], $this->raceOptions('customer-hold-update', 'first')),
            $b->prepare('PUT', $path, ['name' => 'Synthetic later update'], $this->raceOptions('observe', 'second')),
            'manager',
        );
        $this->assertCommitted($first, 200);
        $this->assertCommitted($second, 200);
        $this->assertSame('Synthetic later update', $target->fresh()->name);
        $this->assertNull($target->fresh()->mobile);
        $this->assertSame(CustomerStatus::INACTIVE, $target->fresh()->is_active);
        $this->assertSame(1, Customer::count());
    }

    public function test_live_permission_revocation_is_ordered_against_admitted_customer_writes(): void
    {
        $manager = $this->user('Customer.Revoke.Manager');
        $role = Role::create(['name' => 'Synthetic Customer race writer', 'guard_name' => 'web']);
        $role->givePermissionTo('people.customers.update');
        $staff = $this->user('Customer.Revoke.Staff', $role->name);
        $target = Customer::query()->create(['name' => 'Synthetic protected'])->fresh();
        $before = $target->getRawOriginal();
        $admin = $this->browser($this->server());
        $writer = $this->browser($this->server());
        $this->assertSame(200, $admin->login($manager->username)->status);
        $this->assertSame(200, $writer->login($staff->username)->status);
        $rolePath = '/api/v1/roles/'.$role->id;
        $customerPath = '/api/v1/customers/'.$target->id;

        [$first, $second] = $this->race(
            $admin->prepare('PUT', $rolePath, ['name' => 'Synthetic revoked', 'isActive' => true, 'permissions' => []], $this->raceOptions('hold-role-write', 'first')),
            $writer->prepare('PUT', $customerPath, ['name' => 'Synthetic must not commit'], $this->raceOptions('observe', 'second')),
            'manager',
        );
        $this->assertSame(200, $first->status);
        $this->assertSame(403, $second->status);
        $this->assertSame('rolled_back', $second->header('X-Test-Customer-Outcome'));
        $this->assertSame('0', $second->header('X-Test-Customer-Reads'));
        $this->assertSame('0', $second->header('X-Test-Customer-Writes'));
        $this->assertSame($before, $target->fresh()->getRawOriginal());
        $this->assertSame(200, $writer->me()->status);
        $this->assertSame([], $writer->me()->json()['data']['permissions']);

        $this->assertSame(200, $admin->request('PUT', $rolePath, [
            'name' => 'Synthetic restored', 'isActive' => true, 'permissions' => ['people.customers.update'],
        ])->status);
        [$first, $second] = $this->race(
            $writer->prepare('PUT', $customerPath, ['name' => 'Synthetic authorized before revoke'], $this->raceOptions('customer-hold-update', 'first')),
            $admin->prepare('PUT', $rolePath, ['name' => 'Synthetic revoked again', 'isActive' => true, 'permissions' => []], $this->raceOptions('observe', 'second')),
            'manager',
        );
        $this->assertCommitted($first, 200);
        $this->assertSame(200, $second->status);
        $this->assertSame('Synthetic authorized before revoke', $target->fresh()->name);
        $this->assertSame(403, $writer->request('PUT', $customerPath, ['name' => 'Synthetic must not commit'])->status);
        $this->assertSame($staff->auth_version, $staff->fresh()->auth_version);
    }

    public function test_role_disable_retires_waiting_customer_write_without_restoring_session(): void
    {
        $manager = $this->user('Customer.Disable.Manager');
        $role = Role::create(['name' => 'Synthetic Customer disable writer', 'guard_name' => 'web']);
        $role->givePermissionTo('people.customers.update');
        $staff = $this->user('Customer.Disable.Staff', $role->name);
        $target = Customer::query()->create(['name' => 'Synthetic disable target'])->fresh();
        $before = $target->getRawOriginal();
        $admin = $this->browser($this->server());
        $writer = $this->browser($this->server());
        $this->assertSame(200, $admin->login($manager->username)->status);
        $this->assertSame(200, $writer->login($staff->username)->status);

        [$first, $second] = $this->race(
            $admin->prepare('PUT', '/api/v1/roles/'.$role->id, ['name' => $role->name, 'isActive' => false, 'permissions' => ['people.customers.update']], $this->raceOptions('hold-role-write', 'first')),
            $writer->prepare('PUT', '/api/v1/customers/'.$target->id, ['name' => 'Synthetic must not commit'], $this->raceOptions('observe', 'second')),
            'manager',
        );
        $this->assertSame(200, $first->status);
        $this->assertSame(401, $second->status);
        $this->assertSame('rolled_back', $second->header('X-Test-Customer-Outcome'));
        $this->assertSame('0', $second->header('X-Test-Customer-Reads'));
        $this->assertSame('0', $second->header('X-Test-Customer-Writes'));
        $this->assertSame($before, $target->fresh()->getRawOriginal());
        $this->assertSame($staff->auth_version + 1, $staff->fresh()->auth_version);
        $this->assertSame(0, DB::table('sessions')->where('auth_user_id', $staff->id)->count());
        $this->assertSame(200, $admin->request('PUT', '/api/v1/roles/'.$role->id, [
            'name' => $role->name, 'isActive' => true, 'permissions' => ['people.customers.update'],
        ])->status);
        $this->assertSame(401, $writer->me()->status);
    }

    private function assertCommitted(AuthenticationHttpResponse $response, int $status): void
    {
        $this->assertSame($status, $response->status, $response->header('X-Test-Exception-Class') ?? '');
        $this->assertSame('committed', $response->header('X-Test-Customer-Outcome'));
        $this->assertSame('1', $response->header('X-Test-Customer-Writes'));
    }

    private function assertDuplicate(AuthenticationHttpResponse $response): void
    {
        $this->assertSame(409, $response->status);
        $this->assertSame(['success' => false, 'message' => __('people.customers.mobile_duplicate'), 'errors' => ['code' => 'CUSTOMER_MOBILE_ALREADY_EXISTS']], $response->json());
        $this->assertSame('1062', $response->header('X-Test-Customer-MySQL-Code'));
        $this->assertSame('1', $response->header('X-Test-Customer-MySQL-Count'));
        $this->assertSame('rolled_back', $response->header('X-Test-Customer-Outcome'));
        $this->assertSame('1', $response->header('X-Test-Customer-Writes'));
    }
}
