<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Modules\Activities\Http\Middleware\RequireActivitiesCapability;
use Modules\Activities\Models\Activity;
use Modules\Identity\Http\Middleware\RequireStaffSession;
use Modules\Identity\Http\Middleware\RequireStatefulBrowser;
use Modules\Identity\Models\Permission;
use Modules\Identity\Models\Role;
use Tests\Support\ActivityHttpFixtures;
use Tests\Support\AuthenticationRaceHarness;
use Tests\Support\UsersRaceFixtures;

class ActivityReadHttpTest extends AuthenticationHttpTestCase
{
    use ActivityHttpFixtures, AuthenticationRaceHarness, UsersRaceFixtures;

    public function test_exact_resources_all_filters_sorts_paging_and_query_bounds(): void
    {
        $actor = $this->user('Activity.Read.Manager');
        $browser = $this->browser($this->server());
        $this->assertSame(200, $browser->login($actor->username)->status);
        $empty = $browser->request('GET', '/api/v1/activities');
        $this->assertSame(200, $empty->status);
        $this->assertSame(['success' => true, 'message' => '', 'data' => [
            'items' => [], 'pagination' => ['total' => 0, 'currentPage' => 1, 'perPage' => 15, 'lastPage' => 1],
        ]], $empty->json());
        $this->assertSame('1', $empty->header('X-Test-Activity-Reads'));

        $hourly = $this->activityFixture('سباحة %_!\\,alpha', ['is_active' => false]);
        $monthly = $this->activityFixture('Nursery', [
            'supports_hourly' => false, 'hourly_rate' => null, 'supports_subscription' => true, 'monthly_price' => '800.00',
        ]);
        $both = $this->activityFixture('سباحة Mixed', [
            'supports_subscription' => true, 'monthly_price' => '9999999999.99', 'hourly_rate' => '0.01',
        ]);
        $before = $this->activitySnapshot();

        foreach ([$hourly, $monthly, $both] as $activity) {
            $response = $browser->request('GET', '/api/v1/activities/'.$activity->id);
            $this->assertSame(200, $response->status);
            $this->assertSame(['success' => true, 'message' => '', 'data' => $this->activityData($activity)], $response->json());
            $this->assertSame('2', $response->header('X-Test-Activity-Reads'));
            $this->assertSame('1', $response->header('X-Test-Activity-Target-Reads'));
            $this->assertSame('0', $response->header('X-Test-Activity-Writes'));
        }

        foreach ([
            [[], [$both->id, $monthly->id, $hourly->id]],
            [['search' => ''], [$both->id, $monthly->id, $hourly->id]],
            [['search' => 'سباحة'], [$both->id, $hourly->id]],
            [['search' => 'nursery'], [$monthly->id]],
            [['search' => '%'], [$hourly->id]],
            [['search' => '_'], [$hourly->id]],
            [['search' => '!'], [$hourly->id]],
            [['search' => '\\'], [$hourly->id]],
            [['search' => ',alpha'], [$hourly->id]],
            [['search' => "' OR 1=1 --"], []],
            [['billingMode' => '0'], [$both->id, $hourly->id]],
            [['billingMode' => '1'], [$both->id, $monthly->id]],
            [['isActive' => '0'], [$hourly->id]],
            [['isActive' => '1'], [$both->id, $monthly->id]],
            [['billingMode' => '0', 'isActive' => '0', 'search' => 'سباحة'], [$hourly->id]],
            [['billingMode' => '1', 'isActive' => '0'], []],
        ] as [$filter, $ids]) {
            $response = $browser->request('GET', '/api/v1/activities?'.http_build_query(['filter' => $filter]));
            $this->assertSame(200, $response->status, json_encode($filter, JSON_UNESCAPED_UNICODE));
            $data = $response->json()['data'];
            $this->assertSame($ids, array_column($data['items'], 'id'));
            $this->assertSame(['total' => count($ids), 'currentPage' => 1, 'perPage' => 15, 'lastPage' => 1], $data['pagination']);
            $this->assertSame($ids === [] ? '1' : '2', $response->header('X-Test-Activity-Reads'));
            $this->assertSame('0', $response->header('X-Test-Activity-Writes'));
        }
        foreach (['id', '-id', 'name', '-name'] as $sort) {
            $column = ltrim($sort, '-');
            $query = DB::table('activities')->orderBy($column, str_starts_with($sort, '-') ? 'desc' : 'asc');
            if ($column === 'name') {
                $query->orderBy('id');
            }
            $response = $browser->request('GET', '/api/v1/activities?sort='.$sort);
            $this->assertSame(200, $response->status);
            $this->assertSame($query->pluck('id')->all(), array_column($response->json()['data']['items'], 'id'));
        }
        $this->assertSame($before, $this->activitySnapshot());

        $rows = [];
        for ($i = 1; $i <= 120; $i++) {
            $rows[] = ['name' => 'Fixture page '.$i, 'supports_hourly' => true, 'supports_subscription' => false, 'hourly_rate' => '60.00'];
        }
        DB::table('activities')->insert($rows);
        foreach ([['', 15, 1, 15], ['?perPage=500', 100, 1, 100], ['?perPage=100&page=02', 100, 2, 23]] as [$params, $size, $page, $count]) {
            $response = $browser->request('GET', '/api/v1/activities'.$params);
            $this->assertSame(200, $response->status);
            $data = $response->json()['data'];
            $this->assertSame(['total' => 123, 'currentPage' => $page, 'perPage' => $size, 'lastPage' => (int) ceil(123 / $size)], $data['pagination']);
            $this->assertCount($count, $data['items']);
            $this->assertSame(DB::table('activities')->orderByDesc('id')->forPage($page, $size)->pluck('id')->all(), array_column($data['items'], 'id'));
            $this->assertSame('2', $response->header('X-Test-Activity-Reads'));
            $this->assertSame((string) $size, $response->header('X-Test-Activity-Page-Limit'));
        }
        $response = $browser->request('GET', '/api/v1/activities?page='.PHP_INT_MAX.'&perPage='.PHP_INT_MAX);
        $this->assertSame(200, $response->status);
        $this->assertSame(['items' => [], 'pagination' => ['total' => 123, 'currentPage' => PHP_INT_MAX, 'perPage' => 100, 'lastPage' => 2]], $response->json()['data']);
        $this->assertSame('1', $response->header('X-Test-Activity-Reads'));
    }

    public function test_read_authority_transport_and_exact_route_inventory(): void
    {
        $server = $this->server();
        $anonymous = $this->browser($server);
        $this->assertSame(401, $anonymous->request('GET', '/api/v1/activities')->status);
        foreach ([
            ['Origin' => 'https://unapproved.invalid'],
            ['Origin' => null, 'Referer' => null],
            ['Authorization' => 'Bearer synthetic-token'],
            ['Host' => 'unapproved.invalid'],
        ] as $headers) {
            $response = $anonymous->request('GET', '/api/v1/activities', options: ['headers' => $headers]);
            $this->assertSame(403, $response->status);
            $this->assertSame('0', $response->header('X-Test-Activity-Reads'));
        }
        $manager = $this->user('Activity.Boundary.Manager');
        $admin = $this->browser($server);
        $this->assertSame(200, $admin->login($manager->username)->status);
        $target = $this->activityFixture('Boundary activity');
        foreach (['?sort=hourlyRate', '?sort=id,-name', '?sort[]=id', '?sort=', '?include=anything',
            '?fields[name]=x', '?filter=0', '?filter[unknown]=x', '?filter[search][]=a', '?filter[search]=%FF',
            '?filter[search]='.str_repeat('a', 256), '?filter[isActive]=false', '?filter[isActive]=01',
            '?filter[billingMode]=2', '?filter[billingMode][]=0', '?filter[billingMode]=', '?page=0', '?page=-1',
            '?page=9223372036854775808', '?perPage[]=10', '?perPage=0', '?perPage=1.0'] as $query) {
            $response = $admin->request('GET', '/api/v1/activities'.$query);
            $this->assertSame(422, $response->status, $query);
            $this->assertSame('0', $response->header('X-Test-Activity-Reads'));
            $this->assertArrayNotHasKey('data', $response->json());
        }
        $this->assertSame(422, $admin->request('GET', '/api/v1/activities', options: ['raw' => '{}'])->status);
        $this->assertSame(422, $admin->request('GET', '/api/v1/activities/'.$target->id.'?extra=1')->status);
        foreach (['0', '-1', 'abc', '01', '9223372036854775808', '999999'] as $id) {
            $this->assertSame(404, $admin->request('GET', '/api/v1/activities/'.$id)->status);
        }

        $expected = [
            'GET api/v1/activities' => 'activities.index',
            'GET api/v1/activities/{activity}' => 'activities.show',
            'POST api/v1/activities' => 'activities.store',
            'PUT api/v1/activities/{activity}' => 'activities.update',
            'DELETE api/v1/activities/{activity}' => 'activities.delete',
        ];
        foreach (Route::getRoutes() as $route) {
            $key = $route->methods()[0].' '.$route->uri();
            if (! isset($expected[$key])) {
                continue;
            }
            foreach ([RequireStatefulBrowser::class, 'auth:sanctum', RequireStaffSession::class, RequireActivitiesCapability::class.':'.$expected[$key]] as $middleware) {
                $this->assertContains($middleware, $route->gatherMiddleware());
            }
            unset($expected[$key]);
        }
        $this->assertSame([], $expected);
    }

    public function test_capabilities_are_independent_and_missing_target_is_not_disclosed(): void
    {
        $server = $this->server();
        foreach (['index', 'show', 'store', 'update', 'delete'] as $capability) {
            $role = Role::create(['name' => 'Activity '.$capability, 'guard_name' => 'web']);
            $role->givePermissionTo(Permission::findByName('activities.'.$capability, 'web'));
            $actor = $this->user('Activity.Only.'.$capability, $role->name);
            $browser = $this->browser($server);
            $this->assertSame(200, $browser->login($actor->username)->status);
            $target = $this->activityFixture('Target '.$capability);
            $path = '/api/v1/activities/'.$target->id;
            foreach ([
                ['index', 'GET', '/api/v1/activities', null, 200],
                ['show', 'GET', $path, null, 200],
                ['store', 'POST', '/api/v1/activities', $this->activityFields('Created '.$capability), 201],
                ['update', 'PUT', $path, $this->activityFields('Changed '.$capability), 200],
                ['delete', 'DELETE', $path, null, 200],
            ] as [$required, $method, $url, $body, $success]) {
                $response = $browser->request($method, $url, $body);
                $this->assertSame($required === $capability ? $success : 403, $response->status, $capability.' '.$required);
                if ($required !== $capability) {
                    $this->assertSame('0', $response->header('X-Test-Activity-Reads'));
                    $this->assertSame('0', $response->header('X-Test-Activity-Writes'));
                    $this->assertArrayNotHasKey('data', $response->json());
                }
            }
            foreach (['show' => 'GET', 'update' => 'PUT', 'delete' => 'DELETE'] as $required => $method) {
                $response = $browser->request($method, '/api/v1/activities/999999', $method === 'PUT' ? $this->activityFields('Missing') : null);
                $this->assertSame($required === $capability ? 404 : 403, $response->status);
                $this->assertSame($required === $capability ? '1' : '0', $response->header('X-Test-Activity-Target-Reads'));
            }
            $select = $browser->request('GET', '/api/v1/selects?selects=activities,activeActivities');
            $this->assertSame($capability === 'index' ? 200 : 403, $select->status);
            $this->assertSame($capability === 'index' ? '2' : '0', $select->header('X-Test-Activity-Reads'));
        }
    }

    public function test_count_and_page_share_snapshot_and_detail_reresolves_deleted_target(): void
    {
        $manager = $this->user('Activity.Snapshot.Manager');
        $a = $this->browser($this->server());
        $b = $this->browser($this->server());
        $this->assertSame(200, $a->login($manager->username)->status);
        $this->assertSame(200, $b->login($manager->username)->status);
        $old = $this->activityFixture('Snapshot original');
        [$first, $second] = $this->race(
            $a->prepare('GET', '/api/v1/activities', options: $this->raceOptions('activity-hold-page', 'first')),
            $b->prepare('POST', '/api/v1/activities', $this->activityFields('Snapshot later'), $this->raceOptions('observe', 'second')),
            null,
        );
        $this->assertSame(201, $second->status);
        $this->assertSame(200, $first->status);
        $this->assertSame(['items' => [$this->activityData($old)], 'pagination' => ['total' => 1, 'currentPage' => 1, 'perPage' => 15, 'lastPage' => 1]], $first->json()['data']);
        $this->assertSame('2', $first->header('X-Test-Activity-Reads'));
        $this->assertSame(2, Activity::count());

        [$first, $second] = $this->race(
            $a->prepare('GET', '/api/v1/activities/'.$old->id, options: $this->raceOptions('activity-hold-detail', 'first')),
            $b->prepare('DELETE', '/api/v1/activities/'.$old->id, options: $this->raceOptions('observe', 'second')),
            null,
        );
        $this->assertSame(200, $second->status);
        $this->assertSame(404, $first->status);
        $this->assertSame('2', $first->header('X-Test-Activity-Reads'));
        $this->assertSame('1', $first->header('X-Test-Activity-Target-Reads'));
        $this->assertFalse(Activity::query()->whereKey($old->id)->exists());
    }
}
