<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Identity\Models\Role;
use Modules\Identity\Models\User;
use Tests\Support\AuthenticationBrowser;
use Tests\Support\AuthenticationHttpResponse;

class UsersManagementReadTest extends AuthenticationHttpTestCase
{
    public function test_explicit_list_detail_and_me_contracts_and_anomalous_assignments(): void
    {
        $actor = $this->user('Fixture.Manager');
        $browser = $this->browser($this->server());
        $this->assertSame(200, $browser->login($actor->username)->status);
        $staff = $this->user('Exact.Staff', 'reception', [
            'name' => 'موظف تجريبي', 'email' => 'Case@example.test', 'phone' => '00012345678', 'is_active' => false,
        ]);
        DB::table('users')->where('id', $staff->id)->update(['avatar_key' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.png']);
        $missing = User::factory()->create(['username' => 'Fixture.NoRole']);
        $wrong = User::factory()->create(['username' => 'Fixture.WrongGuard']);
        $wrongRole = Role::create(['name' => 'fixture-other', 'guard_name' => 'other']);
        DB::table('model_has_roles')->insert(['role_id' => $wrongRole->id, 'model_type' => $wrong->getMorphClass(), 'model_id' => $wrong->id]);
        $reception = Role::findByName('reception', 'web');
        $reception->update(['is_active' => false]);
        $response = $this->index($browser);
        $data = $this->success($response);
        $this->assertSame(['items', 'pagination'], array_keys($data));
        $this->assertSame(['total' => 4, 'currentPage' => 1, 'perPage' => 15, 'lastPage' => 1], $data['pagination']);
        $this->assertSame([$wrong->id, $missing->id, $staff->id, $actor->id], array_column($data['items'], 'userId'));
        $this->assertNull($data['items'][0]['role']);
        $this->assertNull($data['items'][1]['role']);
        $row = $data['items'][2];
        $this->assertSame([
            'userId' => $staff->id, 'avatarUrl' => '/api/v1/users/'.$staff->id.'/avatar', 'name' => 'موظف تجريبي',
            'username' => 'Exact.Staff', 'phone' => '00012345678', 'isActive' => false,
            'role' => ['id' => $reception->id, 'name' => 'reception', 'isActive' => false],
        ], $row);
        $this->assertSame('1', $response->header('X-Test-Users-Count-Reads'));
        $this->assertSame('1', $response->header('X-Test-Users-Page-Reads'));
        $this->assertSame('1', $response->header('X-Test-Users-Role-Reads'));
        $this->assertSame('1', $response->header('X-Test-Users-Snapshot'));
        $detail = $browser->request('GET', '/api/v1/users/'.$staff->id);
        $this->assertSame($row + ['email' => 'Case@example.test'], $this->success($detail));
        $this->assertSame('1', $detail->header('X-Test-Users-Target-Reads'));
        $this->assertSame('1', $detail->header('X-Test-Users-Role-Reads'));
        $this->assertSame(404, $browser->request('GET', '/api/v1/users/999999')->status);
        $this->assertSame(422, $browser->request('GET', '/api/v1/users/'.$staff->id.'?include=roles')->status);
        $this->assertSame(['name', 'username', 'role', 'permissions'], array_keys($this->success($browser->me())));
    }

    public function test_literal_search_grouping_and_independent_role_state_filters(): void
    {
        $actor = $this->user('Fixture.Manager', attributes: ['name' => 'Operator']);
        $browser = $this->browser($this->server());
        $this->assertSame(200, $browser->login($actor->username)->status);
        $a = $this->user('Case.User', 'reception', ['name' => 'أحمد 100%_!,false', 'phone' => '00012345678', 'is_active' => false]);
        $b = $this->user('case.user', 'reception', ['name' => 'أحمد 100AB,true', 'phone' => '01123456789']);
        $c = $this->user('Other.User', 'manager', ['name' => 'أحمد false', 'phone' => '02234567890', 'is_active' => false]);
        $d = $this->user('Comma.User', 'reception', ['name' => 'alpha,beta', 'phone' => '03345678912']);
        $e = $this->user('Alpha.User', 'reception', ['name' => 'alpha', 'phone' => '04456789123']);
        $f = $this->user('Mail.Only', 'reception', ['name' => 'Other', 'email' => 'hiddenneedle@example.test', 'phone' => null]);
        $role = Role::findByName('reception', 'web');
        $role->update(['is_active' => false]);
        foreach ([
            ['Case.User', [$a->id]], ['case.user', [$b->id]], ['000123', [$a->id]],
            ['100%_!', [$a->id]], ['%', [$a->id]], ['_', [$a->id]], ['!', [$a->id]],
            ['alpha,beta', [$d->id]], ['false', [$c->id, $a->id]], ['true', [$b->id]],
            ['0', [$e->id, $d->id, $c->id, $b->id, $a->id]], ['أحمد', [$c->id, $b->id, $a->id]],
            ['hiddenneedle', []], ['  Case.User  ', [$a->id]], ["%' OR 1=1 --", []],
        ] as [$search, $ids]) {
            $response = $this->index($browser, ['filter' => ['search' => $search]]);
            $this->assertSame(200, $response->status, $search.'; '.($response->header('X-Test-Exception-Class') ?? 'no exception class'));
            $this->assertSame($ids, $this->ids($response), $search);
        }
        foreach (['0', 'false'] as $state) {
            $response = $this->index($browser, ['filter' => ['search' => 'أحمد', 'roleId' => $role->id, 'isActive' => $state]]);
            $data = $this->success($response);
            $this->assertSame([$a->id], array_column($data['items'], 'userId'));
            $this->assertSame(1, $data['pagination']['total']);
        }
        $this->assertSame([$f->id, $e->id, $d->id, $b->id], $this->ids($this->index($browser, ['filter' => ['roleId' => $role->id, 'isActive' => 'true']])));
        $this->assertSame([], $this->ids($this->index($browser, ['filter' => ['roleId' => 999999]])));
        $this->assertSame(7, $this->success($this->index($browser, ['filter' => ['search' => '  ']]))['pagination']['total']);
    }

    public function test_bounded_pagination_and_identical_count_page_predicates(): void
    {
        $actor = $this->user('Fixture.Manager');
        $browser = $this->browser($this->server());
        $this->assertSame(200, $browser->login($actor->username)->status);
        // Reuse only a synthetic hash to avoid measuring password hashing here.
        $rows = [];
        for ($i = 1; $i <= 110; $i++) {
            $rows[] = ['name' => 'Fixture bulk', 'username' => 'Bulk.'.$i, 'password' => $actor->password, 'is_active' => $i % 2 === 0];
        }
        DB::table('users')->insert($rows);
        foreach ([[[], 15, 15, 8], [['perPage' => 200], 100, 100, 2], [['perPage' => 100, 'page' => 2], 100, 11, 2]] as [$query, $size, $count, $last]) {
            $response = $this->index($browser, $query);
            $data = $this->success($response);
            $this->assertCount($count, $data['items']);
            $this->assertSame(['total' => 111, 'currentPage' => $query['page'] ?? 1, 'perPage' => $size, 'lastPage' => $last], $data['pagination']);
            $this->assertSame('1', $response->header('X-Test-Users-Count-Reads'));
            $this->assertSame('1', $response->header('X-Test-Users-Page-Reads'));
            $this->assertSame('1', $response->header('X-Test-Users-Role-Reads'));
            $this->assertSame((string) $size, $response->header('X-Test-Users-Page-Limit'));
            $ids = array_column($data['items'], 'userId');
            $sorted = $ids;
            rsort($sorted);
            $this->assertSame($sorted, $ids);
        }
        $data = $this->success($this->index($browser, ['filter' => ['search' => 'Bulk.', 'isActive' => '0'], 'page' => 2, 'perPage' => 20]));
        $this->assertSame(['total' => 55, 'currentPage' => 2, 'perPage' => 20, 'lastPage' => 3], $data['pagination']);
        $this->assertCount(20, $data['items']);
        $this->assertSame(array_fill(0, 20, false), array_column($data['items'], 'isActive'));
        foreach ([['page' => PHP_INT_MAX], ['filter' => ['search' => 'not-found-needle']]] as $query) {
            $response = $this->index($browser, $query);
            $data = $this->success($response);
            $this->assertSame([], $data['items']);
            $this->assertSame($query['page'] ?? 1, $data['pagination']['currentPage']);
            $this->assertSame(isset($query['page']) ? 111 : 0, $data['pagination']['total']);
            $this->assertSame('1', $response->header('X-Test-Users-Count-Reads'));
            $this->assertSame('0', $response->header('X-Test-Users-Page-Reads'));
            $this->assertSame('0', $response->header('X-Test-Users-Role-Reads'));
        }
    }

    public function test_invalid_query_shapes_and_overflow_are_rejected_before_domain_reads(): void
    {
        $actor = $this->user('Fixture.Manager');
        $browser = $this->browser($this->server());
        $this->assertSame(200, $browser->login($actor->username)->status);
        foreach ([
            'sort=id', 'include=roles', 'fields[users]=password', 'extra=1', 'filter=search', 'filter=',
            'filter[unknown]=1', 'filter[search][]=a', 'filter[roleId][]=1', 'filter[isActive][]=0',
            'filter[search]='.str_repeat('a', 256), 'filter[search]=%FF', 'filter[roleId]=0',
            'filter[roleId]=-1', 'filter[roleId]=2.0', 'filter[roleId]=18446744073709551616',
            'filter[isActive]=False', 'filter[isActive]=yes', 'filter[isActive]=', 'filter[isActive]=%200',
            'page=0', 'page=-1', 'page=1.0', 'page[]=1', 'page=9223372036854775808',
            'perPage=0', 'perPage[]=1', 'perPage=9223372036854775808', 'perPage=%201',
        ] as $query) {
            $response = $browser->request('GET', '/api/v1/users?'.$query);
            $this->assertSame(422, $response->status, $query);
            $this->assertSame(false, $response->json()['success']);
            $this->assertTrue(Str::isUuid($response->header('X-Request-ID')));
            $this->assertSame('0', $response->header('X-Test-Users-Count-Reads'));
            $this->assertSame('0', $response->header('X-Test-Users-Page-Reads'));
            $this->assertSame('0', $response->header('X-Test-Users-Role-Reads'));
        }
        $this->assertSame(422, $browser->request('GET', '/api/v1/users', options: ['raw' => '{"filter":{"isActive":"0"}}'])->status);
        $this->assertSame(200, $this->index($browser, ['filter' => ['search' => str_repeat('a', 255)]])->status);
    }

    private function index(AuthenticationBrowser $browser, array $query = []): AuthenticationHttpResponse
    {
        return $browser->request('GET', '/api/v1/users'.($query === [] ? '' : '?'.http_build_query($query)));
    }

    private function ids(AuthenticationHttpResponse $response): array
    {
        return array_column($this->success($response)['items'], 'userId');
    }

    private function success(AuthenticationHttpResponse $response): array
    {
        $this->assertSame(200, $response->status);
        $this->assertTrue(Str::isUuid($response->header('X-Request-ID')));
        $data = $response->json();
        $this->assertSame(['success', 'message', 'data'], array_keys($data));
        $this->assertTrue($data['success']);
        $this->assertSame('', $data['message']);

        return $data['data'];
    }
}
