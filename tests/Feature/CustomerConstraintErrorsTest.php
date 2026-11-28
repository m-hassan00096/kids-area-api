<?php

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Support\ApiErrors;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\People\Services\Customers\CustomerConstraintErrors;

class CustomerConstraintErrorsTest extends IdentityDatabaseTestCase
{
    public function test_only_native_named_mobile_conflicts_map_after_rollback(): void
    {
        $first = DB::table('customers')->insertGetId(['name' => 'Synthetic first', 'mobile' => '00000000001', 'is_active' => 0]);
        $second = DB::table('customers')->insertGetId(['name' => 'Synthetic second', 'mobile' => null]);
        $before = DB::table('customers')->orderBy('id')->get()->all();
        $mapper = app(CustomerConstraintErrors::class);
        $request = Request::create('/api/v1/customers', 'POST');

        foreach ([
            fn () => DB::table('customers')->insert(['name' => 'Synthetic duplicate', 'mobile' => '00000000001']),
            fn () => DB::table('customers')->where('id', $second)->update(['mobile' => '00000000001']),
        ] as $operation) {
            $failure = $this->rolledBackFailure($operation);
            $this->assertSame(1062, (int) $failure->errorInfo[1]);
            $mapped = $mapper->translate($failure);
            $this->assertInstanceOf(BusinessException::class, $mapped);
            $this->assertSame(409, $mapped->httpStatus);
            $this->assertSame('CUSTOMER_MOBILE_ALREADY_EXISTS', $mapped->errorCode);
            $response = ApiErrors::render($mapped, $request);
            $this->assertNotNull($response);
            $this->assertSame(409, $response->getStatusCode());
            $this->assertSame([
                'success' => false,
                'message' => 'رقم الموبايل مسجل بالفعل.',
                'errors' => ['code' => 'CUSTOMER_MOBILE_ALREADY_EXISTS'],
            ], $response->getData(true));
        }

        DB::table('identity_feature_provisions')->insert(['feature_key' => 'activities.catalogue.v1', 'completed_at' => '2026-11-28 08:00:00']);
        foreach ([
            [1062, fn () => DB::table('customers')->insert(['id' => $first, 'name' => 'Synthetic other'])],
            [3819, fn () => DB::table('customers')->where('id', $second)->update(['is_active' => 2])],
            [1062, fn () => DB::table('identity_feature_provisions')->insert(['feature_key' => 'activities.catalogue.v1', 'completed_at' => '2026-11-28 08:00:00'])],
        ] as [$code, $operation]) {
            $failure = $this->rolledBackFailure($operation);
            $this->assertSame($code, (int) $failure->errorInfo[1]);
            $this->assertSame($failure, $mapper->translate($failure));
            $response = ApiErrors::render($failure, $request);
            $this->assertNotNull($response);
            $this->assertSame(500, $response->getStatusCode());
            $this->assertSame(['success' => false, 'message' => __('api.errors.internal'), 'errors' => []], $response->getData(true));
        }
        $this->assertEquals($before, DB::table('customers')->orderBy('id')->get()->all());
    }

    private function rolledBackFailure(Closure $operation): QueryException
    {
        try {
            DB::transaction($operation, 1);
        } catch (QueryException $failure) {
            $this->assertSame(0, DB::transactionLevel());

            return $failure;
        }

        $this->fail('Expected a native MySQL constraint rejection.');
    }
}
