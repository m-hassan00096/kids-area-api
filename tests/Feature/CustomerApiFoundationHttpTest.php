<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\People\Enums\CustomerStatus;
use Modules\People\Models\Customer;

class CustomerApiFoundationHttpTest extends AuthenticationHttpTestCase
{
    public function test_native_customer_create_detail_and_full_update_contract(): void
    {
        $server = $this->server();
        $actor = $this->user('Customer.Foundation.Manager');
        $staff = $this->user('Customer.Foundation.Reception', 'reception');
        $manager = $this->browser($server);
        $reception = $this->browser($server);
        $this->assertSame(200, $manager->login($actor->username)->status);
        $this->assertSame(200, $reception->login($staff->username)->status);

        $response = $manager->request('POST', '/api/v1/customers', ['name' => "\u{00A0}عميل تجريبي\u{2003}", 'mobile' => ' 00000000001 ']);
        $this->assertSame(201, $response->status, $response->header('X-Test-Exception-Class') ?? '');
        $id = $response->json()['data']['id'];
        $this->assertIsInt($id);
        $data = ['id' => $id, 'name' => 'عميل تجريبي', 'mobile' => '00000000001'];
        $this->assertSame(['success' => true, 'message' => __('people.customers.created'), 'data' => $data], $response->json());
        $this->assertTrue(Str::isUuid($response->header('X-Request-ID')));
        $this->assertSame(CustomerStatus::ACTIVE, Customer::query()->findOrFail($id)->is_active);
        $path = '/api/v1/customers/'.$id;
        $this->assertSame(['success' => true, 'message' => '', 'data' => $data], $reception->request('GET', $path)->json());
        foreach ([null, '', "\u{00A0} "] as $mobile) {
            $response = $reception->request('POST', '/api/v1/customers', ['name' => 'عميل تجريبي', 'mobile' => $mobile]);
            $this->assertSame(201, $response->status);
            $this->assertNull($response->json()['data']['mobile']);
        }
        $this->assertSame(201, $reception->request('POST', '/api/v1/customers', ['name' => 'عميل تجريبي'])->status);
        $this->assertSame(5, Customer::count());
        $this->assertSame(4, Customer::query()->whereNull('mobile')->count());

        $duplicate = ['success' => false, 'message' => __('people.customers.mobile_duplicate'), 'errors' => ['code' => 'CUSTOMER_MOBILE_ALREADY_EXISTS']];
        DB::table('customers')->where('id', $id)->update(['is_active' => CustomerStatus::INACTIVE->value]);
        $response = $reception->request('POST', '/api/v1/customers', ['name' => 'Synthetic duplicate', 'mobile' => '00000000001']);
        $this->assertSame(409, $response->status);
        $this->assertSame($duplicate, $response->json());
        $this->assertSame($data, $reception->request('GET', $path)->json()['data']);
        $response = $reception->request('PUT', $path, ['name' => 'Synthetic renamed', 'mobile' => '00000000001']);
        $this->assertSame(200, $response->status);
        $this->assertSame(['success' => true, 'message' => __('people.customers.updated'), 'data' => []], $response->json());
        $this->assertSame(CustomerStatus::INACTIVE, Customer::query()->findOrFail($id)->is_active);
        $other = Customer::query()->whereNull('mobile')->orderBy('id')->firstOrFail();
        $before = $other->getRawOriginal();
        $response = $reception->request('PUT', '/api/v1/customers/'.$other->id, ['name' => 'Synthetic conflict', 'mobile' => '00000000001']);
        $this->assertSame(409, $response->status);
        $this->assertSame($duplicate, $response->json());
        $this->assertSame($before, $other->fresh()->getRawOriginal());

        $response = $manager->request('PUT', $path, ['name' => 'Synthetic cleared']);
        $this->assertSame(200, $response->status);
        $this->assertNull(Customer::query()->findOrFail($id)->mobile);
        $this->assertSame(CustomerStatus::INACTIVE, Customer::query()->findOrFail($id)->is_active);
        $this->assertSame(5, Customer::count());
    }

    public function test_native_rejections_keep_rows_unchanged_and_only_approved_routes_exist(): void
    {
        $actor = $this->user('Customer.Foundation.Validation');
        $server = $this->server();
        $admin = $this->browser($server);
        $this->assertSame(200, $admin->login($actor->username)->status);
        $customer = Customer::query()->create(['name' => 'Synthetic original', 'mobile' => '00000000001']);
        $path = '/api/v1/customers/'.$customer->id;
        // Compare two hydrated persisted rows, not Eloquent insertion order.
        $before = $customer->fresh()->getRawOriginal();
        foreach ([
            [['mobile' => null], 'name'],
            [['name' => null], 'name'],
            [['name' => ' '], 'name'],
            [['name' => "\u{00A0}\u{2003}"], 'name'],
            [['name' => ['Synthetic']], 'name'],
            [['name' => str_repeat('س', 256)], 'name'],
            [['name' => 12], 'name'],
            [['name' => 'Synthetic', 'mobile' => 12345678901], 'mobile'],
            [['name' => 'Synthetic', 'mobile' => '٠٠٠٠٠٠٠٠٠٠١'], 'mobile'],
            [['name' => 'Synthetic', 'mobile' => '00000 00000'], 'mobile'],
            [['name' => 'Synthetic', 'mobile' => '123'], 'mobile'],
            [['name' => 'Synthetic', 'mobile' => '000000000001'], 'mobile'],
            [['name' => 'Synthetic', 'mobile' => '0000000000a'], 'mobile'],
            [['name' => 'Synthetic', 'mobile' => ['00000000001']], 'mobile'],
            [['name' => 'Synthetic', 'isActive' => true], 'request'],
            [['name' => 'Synthetic', 'is_active' => 1], 'request'],
            [['name' => 'Synthetic', 'phone' => '00000000002'], 'request'],
            [['name' => 'Synthetic', 'id' => 1], 'request'],
        ] as [$payload, $field]) {
            foreach ([['POST', '/api/v1/customers'], ['PUT', $path]] as [$method, $target]) {
                $response = $admin->request($method, $target, $payload);
                $this->assertSame(422, $response->status, $response->header('X-Test-Exception-Class') ?? '');
                $this->assertArrayHasKey($field, $response->json()['errors']);
                $this->assertArrayNotHasKey('data', $response->json());
            }
        }
        $this->assertSame(400, $admin->request('POST', '/api/v1/customers', options: ['raw' => '{"name":'])->status);
        $this->assertSame(422, $admin->request('POST', '/api/v1/customers', options: ['raw' => '[]'])->status);
        $this->assertSame(422, $admin->request('POST', '/api/v1/customers?name=Injected', ['name' => 'Synthetic'])->status);
        $this->assertSame(422, $admin->request('GET', $path.'?extra=1')->status);
        $this->assertSame(404, $admin->request('GET', '/api/v1/customers/999999')->status);
        $this->assertSame(404, $admin->request('PUT', '/api/v1/customers/999999', ['name' => 'Missing'])->status);
        $this->assertSame(404, $admin->request('GET', '/api/v1/customers/9223372036854775808')->status);
        $this->assertSame(405, $admin->request('GET', '/api/v1/customers')->status);
        $this->assertSame(405, $admin->request('DELETE', $path)->status);
        $this->assertSame(405, $admin->request('PATCH', $path, ['name' => 'Forbidden'])->status);
        $this->assertSame(419, $admin->request('PUT', $path, ['name' => 'No CSRF'], ['csrf' => 'bad'])->status);
        $guest = $this->browser($server);
        $this->assertSame(401, $guest->request('GET', $path)->status);
        foreach (['null', 'true', '"x"', '{}'] as $raw) {
            $this->assertSame(422, $admin->request('POST', '/api/v1/customers', options: ['raw' => $raw])->status);
        }
        $this->assertSame(400, $admin->request('POST', '/api/v1/customers', options: ['raw' => '{"name":"'.chr(255).'"}'])->status);
        $this->assertSame(422, $admin->request('POST', '/api/v1/customers', options: [
            'raw' => 'name=Synthetic', 'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
        ])->status);
        $this->assertSame(422, $admin->request('POST', '/api/v1/customers', options: [
            'raw' => "--fixture\r\nContent-Disposition: form-data; name=\"name\"\r\n\r\nSynthetic\r\n--fixture\r\nContent-Disposition: form-data; name=\"file\"; filename=\"fixture.txt\"\r\nContent-Type: text/plain\r\n\r\nSynthetic\r\n--fixture--\r\n",
            'headers' => ['Content-Type' => 'multipart/form-data; boundary=fixture'],
        ])->status);
        foreach (['0', '01', '-1', 'abc'] as $invalidId) {
            $this->assertSame(404, $admin->request('GET', '/api/v1/customers/'.$invalidId)->status);
        }
        $this->assertSame(422, $admin->request('PUT', '/api/v1/customers/999999', ['name' => ''])->status);
        foreach ([
            ['/api/v1/customers', ['name' => 'Synthetic', '_method' => 'PUT'], []],
            [$path, ['name' => 'Synthetic', '_method' => 'PUT'], []],
            [$path.'?_method=PUT', ['name' => 'Synthetic'], []],
            ['/api/v1/customers?_method=PUT', ['name' => 'Synthetic'], []],
            [$path, ['name' => 'Synthetic'], ['headers' => ['X-HTTP-Method-Override' => 'PUT']]],
            ['/api/v1/customers', ['name' => 'Synthetic'], ['headers' => ['X-HTTP-Method-Override' => 'PUT']]],
        ] as [$url, $payload, $options]) {
            $response = $admin->request('POST', $url, $payload, $options);
            $this->assertSame(422, $response->status, 'Customer method override must be refused before dispatch: '.$url);
            $this->assertSame('0', $response->header('X-Test-Customer-Reads'));
            $this->assertSame('0', $response->header('X-Test-Customer-Writes'));
        }
        $this->assertSame(1, Customer::count());
        $this->assertSame($before, $customer->fresh()->getRawOriginal());

        $boundary = str_repeat('س', 255);
        $response = $admin->request('POST', '/api/v1/customers', ['name' => $boundary]);
        $this->assertSame(201, $response->status);
        $this->assertSame($boundary, $response->json()['data']['name']);
        $this->assertNull($response->json()['data']['mobile']);
    }
}
