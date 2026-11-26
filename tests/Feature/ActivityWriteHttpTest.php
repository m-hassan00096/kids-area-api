<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Modules\Activities\Models\Activity;
use Tests\Support\ActivityHttpFixtures;

class ActivityWriteHttpTest extends AuthenticationHttpTestCase
{
    use ActivityHttpFixtures;

    public function test_native_create_modes_and_exact_update_clear_reenable_delete_contract(): void
    {
        $actor = $this->user('Activity.Write.Manager');
        $staff = $this->user('Activity.Write.Staff', 'reception');
        $server = $this->server();
        $admin = $this->browser($server);
        $reception = $this->browser($server);
        $this->assertSame(200, $admin->login($actor->username)->status);
        $this->assertSame(200, $reception->login($staff->username)->status);

        $hourly = ['name' => "\u{00A0}Swimming\u{2003}", 'supportsHourly' => true, 'supportsSubscription' => false, 'hourlyRate' => '60'];
        $monthly = ['name' => 'حضانة', 'supportsHourly' => false, 'supportsSubscription' => true, 'monthlyPrice' => '800.5', 'isActive' => false];
        $both = $this->activityFields('الرسم', ['supportsSubscription' => true, 'hourlyRate' => '0.01', 'monthlyPrice' => '9999999999.99']);
        foreach ([$hourly, $monthly, $both] as $body) {
            $response = $admin->request('POST', '/api/v1/activities', $body);
            $this->assertSame(201, $response->status, $response->header('X-Test-Exception-Class') ?? '');
            $this->assertSame(['success' => true, 'message' => __('activities.created'), 'data' => []], $response->json());
            $this->assertSame('committed', $response->header('X-Test-Activity-Outcome'));
            $this->assertSame('1', $response->header('X-Test-Activity-Writes'));
        }
        $swimming = Activity::query()->where('name', 'Swimming')->sole();
        $nursery = Activity::query()->where('name', 'حضانة')->sole();
        $drawing = Activity::query()->where('name', 'الرسم')->sole();
        $this->assertSame('Swimming', $swimming->name);
        $this->assertTrue($swimming->is_active);
        $this->assertSame('60.00', $swimming->hourly_rate);
        $this->assertNull($swimming->monthly_price);
        $this->assertFalse($nursery->is_active);
        $this->assertNull($nursery->hourly_rate);
        $this->assertSame('800.50', $nursery->monthly_price);
        $this->assertSame('0.01', $drawing->hourly_rate);
        $this->assertSame('9999999999.99', $drawing->monthly_price);

        foreach (['swimming', 'الـرسم', 'حضانة'] as $duplicate) {
            $response = $admin->request('POST', '/api/v1/activities', $this->activityFields($duplicate));
            $this->assertSame(409, $response->status);
            $this->assertSame(['success' => false, 'message' => __('activities.name_duplicate'), 'errors' => ['code' => 'ACTIVITY_NAME_ALREADY_EXISTS']], $response->json());
            $this->assertSame('rolled_back', $response->header('X-Test-Activity-Outcome'));
        }
        $this->assertSame(3, Activity::count());
        $before = $this->activitySnapshot();
        $path = '/api/v1/activities/'.$swimming->id;
        $this->assertSame(403, $reception->request('POST', '/api/v1/activities', $this->activityFields('Forbidden'))->status);
        $this->assertSame(403, $reception->request('PUT', $path, $this->activityFields('Forbidden'))->status);
        $this->assertSame(403, $reception->request('DELETE', $path)->status);
        $this->assertSame(419, $admin->request('POST', '/api/v1/activities', $this->activityFields('No CSRF'), ['csrf' => 'bad'])->status);
        $this->assertSame($before, $this->activitySnapshot());

        $change = ['name' => 'SWIMMING', 'supportsHourly' => false, 'supportsSubscription' => true, 'monthlyPrice' => '100', 'isActive' => false];
        $response = $admin->request('PUT', $path, $change);
        $this->assertSame(200, $response->status);
        $this->assertSame(['success' => true, 'message' => __('activities.updated'), 'data' => []], $response->json());
        $swimming = $swimming->fresh();
        $this->assertSame('SWIMMING', $swimming->name);
        $this->assertNull($swimming->hourly_rate);
        $this->assertSame('100.00', $swimming->monthly_price);
        $this->assertFalse($swimming->is_active);
        $this->assertSame($this->activityData($swimming), $reception->request('GET', $path)->json()['data']);
        $options = $reception->request('GET', '/api/v1/selects?selects=activities,activeActivities')->json()['data'];
        $this->assertContains($swimming->id, array_column($options[0]['options'], 'value'));
        $this->assertNotContains($swimming->id, array_column($options[1]['options'], 'value'));

        $before = $this->activitySnapshot();
        $this->assertSame(422, $admin->request('PUT', $path, array_replace($change, ['supportsHourly' => true]))->status);
        $this->assertSame(409, $admin->request('PUT', $path, array_replace($change, ['name' => 'حضانة']))->status);
        $this->assertSame($before, $this->activitySnapshot());
        $this->assertSame(200, $admin->request('PUT', $path, array_replace($change, ['supportsHourly' => true, 'hourlyRate' => '75.5', 'isActive' => true]))->status);
        $this->assertSame('75.50', $swimming->fresh()->hourly_rate);
        $this->assertTrue($swimming->fresh()->is_active);
        $response = $admin->request('DELETE', $path);
        $this->assertSame(200, $response->status);
        $this->assertSame(['success' => true, 'message' => __('activities.deleted'), 'data' => []], $response->json());
        $this->assertFalse(Activity::query()->whereKey($swimming->id)->exists());
        $this->assertSame(404, $admin->request('DELETE', $path)->status);
        $this->assertSame(404, $reception->request('GET', $path)->status);
        $this->assertNotContains($swimming->id, array_column($reception->request('GET', '/api/v1/selects?selects=activities')->json()['data'][0]['options'], 'value'));
        $this->assertSame(2, DB::table('activities')->count());
    }

    public function test_strict_native_json_types_prices_names_and_full_put_without_mutation_on_error(): void
    {
        $actor = $this->user('Activity.Validation.Manager');
        $admin = $this->browser($this->server());
        $this->assertSame(200, $admin->login($actor->username)->status);
        $target = $this->activityFixture('Existing');
        $path = '/api/v1/activities/'.$target->id;
        $valid = $this->activityFields('Validated');
        $before = $this->activitySnapshot();

        $invalid = [
            'name' => [null, 1, [], '', " \u{00A0}\u{2003}", str_repeat('س', 256)],
            'supportsHourly' => [0, 1, 'true', 'false', null, [], (object) []],
            'supportsSubscription' => [0, 1, 'false', null, []],
            'isActive' => [0, 1, 'true', '', null],
            'hourlyRate' => ['0', '0.0', '0.00', '-0.01', '+60', '0060', '00.01', ' 60 ', "60\n", '1.001', '1.', '.5',
                '1e2', '1,00', '10000000000', '10000000000.00', '', '٦٠', '60٫5', 60, 60.5, true, false, null, ['60'], (object) ['price' => '60']],
            'monthlyPrice' => ['1', 1, false, '', []],
        ];
        foreach ($invalid as $field => $values) {
            foreach ($values as $value) {
                $response = $admin->request('POST', '/api/v1/activities', array_replace($valid, [$field => $value]));
                $this->assertSame(422, $response->status, $field.' '.json_encode($value, JSON_UNESCAPED_UNICODE));
                $this->assertArrayHasKey($field, $response->json()['errors']);
                $this->assertSame('0', $response->header('X-Test-Activity-Writes'));
            }
        }
        foreach (['name', 'supportsHourly', 'supportsSubscription', 'hourlyRate'] as $field) {
            $body = $valid;
            unset($body[$field]);
            $response = $admin->request('POST', '/api/v1/activities', $body);
            $this->assertSame(422, $response->status);
            $this->assertArrayHasKey($field, $response->json()['errors']);
        }
        $response = $admin->request('POST', '/api/v1/activities', array_replace($valid, ['supportsHourly' => false, 'hourlyRate' => null]));
        $this->assertSame(422, $response->status);
        $this->assertArrayHasKey('supportsHourly', $response->json()['errors']);
        $this->assertArrayHasKey('supportsSubscription', $response->json()['errors']);
        $response = $admin->request('POST', '/api/v1/activities', array_replace($valid, ['supportsSubscription' => true]));
        $this->assertSame(422, $response->status);
        $this->assertArrayHasKey('monthlyPrice', $response->json()['errors']);
        foreach (['id', 'createdAt', 'updatedAt', 'billingMode', 'category', '_method'] as $field) {
            $this->assertSame(422, $admin->request('POST', '/api/v1/activities', $valid + [$field => 'PUT'])->status);
        }
        foreach (['[]', 'null', 'true', '"x"', '{}'] as $raw) {
            $this->assertSame(422, $admin->request('POST', '/api/v1/activities', options: ['raw' => $raw])->status);
        }
        foreach (['{"name":', '{"name":"'.chr(255).'"}'] as $raw) {
            $this->assertSame(400, $admin->request('POST', '/api/v1/activities', options: ['raw' => $raw])->status);
        }
        $this->assertSame(422, $admin->request('POST', '/api/v1/activities?extra=1', $valid)->status);
        $this->assertSame(422, $admin->request('POST', $path, $valid, ['headers' => ['X-HTTP-Method-Override' => 'PUT']])->status);
        $missingActive = $valid;
        unset($missingActive['isActive']);
        $response = $admin->request('PUT', $path, $missingActive);
        $this->assertSame(422, $response->status);
        $this->assertArrayHasKey('isActive', $response->json()['errors']);
        $this->assertSame(422, $admin->request('DELETE', $path.'?extra=1')->status);
        $this->assertSame(422, $admin->request('DELETE', $path, ['extra' => true])->status);
        $this->assertSame(405, $admin->request('PATCH', $path, $valid)->status);
        $this->assertSame($before, $this->activitySnapshot());

        foreach (['60' => '60.00', '60.5' => '60.50', '60.50' => '60.50', '0.01' => '0.01', '0.1' => '0.10', '1.99' => '1.99', '9999999999.99' => '9999999999.99'] as $input => $expected) {
            $body = $this->activityFields('Price '.$input, ['hourlyRate' => (string) $input]);
            $this->assertSame(201, $admin->request('POST', '/api/v1/activities', $body)->status);
            $this->assertSame($expected, Activity::query()->where('name', $body['name'])->sole()->hourly_rate);
        }
        $longName = str_repeat('س', 255);
        $this->assertSame(201, $admin->request('POST', '/api/v1/activities', $this->activityFields(' '.$longName.' '))->status);
        $this->assertSame($longName, Activity::query()->where('name', $longName)->sole()->name);
    }
}
