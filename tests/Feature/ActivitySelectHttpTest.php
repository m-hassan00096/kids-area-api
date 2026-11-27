<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\Support\ActivityHttpFixtures;

class ActivitySelectHttpTest extends AuthenticationHttpTestCase
{
    use ActivityHttpFixtures;

    public function test_exact_options_and_order_with_existing_registry_and_all_or_nothing_authority(): void
    {
        $manager = $this->user('Activity.Select.Manager');
        $staff = $this->user('Activity.Select.Staff', 'reception');
        $server = $this->server();
        $admin = $this->browser($server);
        $reception = $this->browser($server);
        $this->assertSame(200, $admin->login($manager->username)->status);
        $this->assertSame(200, $reception->login($staff->username)->status);
        $response = $admin->request('GET', '/api/v1/selects?selects=activities,activeActivities');
        $this->assertSame(200, $response->status);
        $this->assertSame(['success' => true, 'message' => '', 'data' => [
            ['label' => 'activities', 'options' => []], ['label' => 'activeActivities', 'options' => []],
        ]], $response->json());
        $this->assertSame('2', $response->header('X-Test-Select-Reads'));

        $this->activityFixture('Zebra', ['is_active' => false]);
        $this->activityFixture('Alpha');
        $this->activityFixture('سباحة');
        $before = $this->activitySnapshot();
        $options = static fn (bool $active): array => DB::table('activities')->when($active, fn ($q) => $q->where('is_active', true))
            ->orderBy('name')->orderBy('id')->get(['id', 'name'])
            ->map(static fn ($row) => ['value' => (int) $row->id, 'label' => $row->name])->all();
        foreach ([$admin, $reception] as $browser) {
            foreach (['activities,activeActivities', 'activeActivities,activities'] as $keys) {
                $response = $browser->request('GET', '/api/v1/selects?selects='.$keys);
                $this->assertSame(200, $response->status);
                $this->assertSame(array_map(static fn ($key) => ['label' => $key, 'options' => $options($key === 'activeActivities')], explode(',', $keys)), $response->json()['data']);
                $this->assertSame('2', $response->header('X-Test-Select-Reads'));
                $this->assertSame('0', $response->header('X-Test-Select-Has-Limit'));
            }
        }
        foreach (['activities,roles', 'roles,activities', 'activeActivities,permissions', 'activities,activeActivities,roles,userFilterRoles,permissions', 'permissions,userFilterRoles,roles,activeActivities,activities'] as $keys) {
            $response = $reception->request('GET', '/api/v1/selects?selects='.$keys);
            $this->assertSame(403, $response->status);
            $this->assertSame('0', $response->header('X-Test-Select-Reads'));
            $this->assertArrayNotHasKey('data', $response->json());
        }
        foreach ([
            ['permissions', 'activities', 'activeActivities'],
            ['activities', 'activeActivities', 'roles', 'permissions'],
            ['activeActivities', 'permissions', 'userFilterRoles', 'activities', 'roles'],
        ] as $keys) {
            $mixed = $admin->request('GET', '/api/v1/selects?selects='.implode(',', $keys));
            $this->assertSame(200, $mixed->status);
            $this->assertSame($keys, array_column($mixed->json()['data'], 'label'));
            $this->assertSame((string) count($keys), $mixed->header('X-Test-Select-Reads'));
            $this->assertSame('0', $mixed->header('X-Test-Select-Has-Limit'));
        }
        foreach (['activities,activities', 'Activities', 'activities,unknown', 'activities,activeActivities,roles,permissions,userFilterRoles,activities'] as $keys) {
            $response = $admin->request('GET', '/api/v1/selects?selects='.$keys);
            $this->assertSame(422, $response->status);
            $this->assertSame('0', $response->header('X-Test-Select-Reads'));
        }
        $this->assertSame($before, $this->activitySnapshot());
    }

    public function test_complete_activity_options_preserve_independent_active_visibility(): void
    {
        $staff = $this->user('Activity.Select.Complete', 'reception');
        $browser = $this->browser($this->server());
        $this->assertSame(200, $browser->login($staff->username)->status);
        $rows = [];
        for ($i = 1; $i <= 200; $i++) {
            $rows[] = ['name' => 'Fixture '.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'supports_hourly' => true, 'supports_subscription' => false, 'hourly_rate' => '1.00'];
        }
        DB::table('activities')->insert($rows);
        foreach ([200, 201, 202] as $count) {
            if ($count === 201) {
                $this->activityFixture('Inactive extra', ['is_active' => false]);
            } elseif ($count === 202) {
                $this->activityFixture('Active extra');
            }
            $options = static fn (bool $active): array => DB::table('activities')->when($active, fn ($q) => $q->where('is_active', true))
                ->orderBy('name')->orderBy('id')->get(['id', 'name'])
                ->map(static fn ($row) => ['value' => (int) $row->id, 'label' => $row->name])->all();
            $response = $browser->request('GET', '/api/v1/selects?selects=activeActivities,activities');
            $this->assertSame(200, $response->status);
            $this->assertSame(['success' => true, 'message' => '', 'data' => [
                ['label' => 'activeActivities', 'options' => $options(true)],
                ['label' => 'activities', 'options' => $options(false)],
            ]], $response->json());
            $this->assertCount($count === 200 ? 200 : $count - 1, $response->json()['data'][0]['options']);
            $this->assertCount($count, $response->json()['data'][1]['options']);
            $this->assertSame('2', $response->header('X-Test-Select-Reads'));
            $this->assertSame('0', $response->header('X-Test-Select-Has-Limit'));
        }
        $this->assertSame(202, DB::table('activities')->count());
    }
}
