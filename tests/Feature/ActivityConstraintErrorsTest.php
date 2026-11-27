<?php

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Support\ApiErrors;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Activities\Services\ActivityConstraintErrors;

class ActivityConstraintErrorsTest extends IdentityDatabaseTestCase
{
    public function test_real_mysql_name_conflicts_map_to_safe_business_error_after_rollback(): void
    {
        $fields = ['supports_hourly' => 1, 'supports_subscription' => 0, 'hourly_rate' => '60.00'];
        $first = DB::table('activities')->insertGetId(['name' => 'الرسم', 'is_active' => 0, ...$fields]);
        $second = DB::table('activities')->insertGetId(['name' => 'Swimming', ...$fields]);
        $mapper = app(ActivityConstraintErrors::class);
        $request = Request::create('/api/v1/activities', 'POST');

        foreach ([
            fn () => DB::table('activities')->insert(['name' => 'الـرسم', ...$fields]),
            fn () => DB::table('activities')->where('id', $first)->update(['name' => 'swimming']),
        ] as $operation) {
            $failure = $this->rolledBackFailure($operation);
            $this->assertSame(1062, (int) $failure->errorInfo[1]);
            $mapped = $mapper->translate($failure);
            $this->assertInstanceOf(BusinessException::class, $mapped);
            $this->assertSame(409, $mapped->httpStatus);
            $this->assertSame('ACTIVITY_NAME_ALREADY_EXISTS', $mapped->errorCode);
            $response = ApiErrors::render($mapped, $request);
            $this->assertNotNull($response);
            $this->assertSame(409, $response->getStatusCode());
            $this->assertSame([
                'success' => false,
                'message' => 'اسم النشاط مستخدم بالفعل.',
                'errors' => ['code' => 'ACTIVITY_NAME_ALREADY_EXISTS'],
            ], $response->getData(true));
        }
        $this->assertSame(2, DB::table('activities')->count());
        $this->assertSame('الرسم', DB::table('activities')->where('id', $first)->value('name'));
        $this->assertSame('Swimming', DB::table('activities')->where('id', $second)->value('name'));
    }

    public function test_real_other_constraints_remain_generic_unexpected_faults(): void
    {
        $fields = ['supports_hourly' => 1, 'supports_subscription' => 0, 'hourly_rate' => '60.00'];
        $id = DB::table('activities')->insertGetId(['name' => 'Original', ...$fields]);
        DB::table('identity_feature_provisions')->insert([
            'feature_key' => 'activities.catalogue.v1',
            'completed_at' => '2026-11-26 00:00:00',
        ]);
        $mapper = app(ActivityConstraintErrors::class);
        $request = Request::create('/api/v1/activities', 'POST');
        foreach ([
            [1062, fn () => DB::table('activities')->insert(['id' => $id, 'name' => 'Another', ...$fields])],
            [3819, fn () => DB::table('activities')->where('id', $id)->update(['hourly_rate' => null])],
            [1062, fn () => DB::table('identity_feature_provisions')->insert([
                'feature_key' => 'activities.catalogue.v1', 'completed_at' => '2026-11-26 00:00:00',
            ])],
        ] as [$code, $operation]) {
            $failure = $this->rolledBackFailure($operation);
            $this->assertSame($code, (int) $failure->errorInfo[1]);
            $this->assertSame($failure, $mapper->translate($failure));
            $response = ApiErrors::render($failure, $request);
            $this->assertNotNull($response);
            $this->assertSame(500, $response->getStatusCode());
            $this->assertSame([
                'success' => false, 'message' => __('api.errors.internal'), 'errors' => [],
            ], $response->getData(true));
        }
        $this->assertSame('60.00', DB::table('activities')->where('id', $id)->value('hourly_rate'));
        $this->assertSame(1, DB::table('activities')->count());
        $this->assertSame(1, DB::table('identity_feature_provisions')->count());
    }

    private function rolledBackFailure(Closure $operation): QueryException
    {
        try {
            DB::transaction($operation, 1);
        } catch (QueryException $failure) {
            $this->assertSame(0, DB::transactionLevel());

            return $failure;
        }

        $this->fail('Expected actual rejected MySQL operation.');
    }
}
