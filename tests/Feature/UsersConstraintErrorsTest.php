<?php

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\Identity\Models\User;
use Modules\Identity\Services\UserConstraintErrors;

class UsersConstraintErrorsTest extends IdentityDatabaseTestCase
{
    public function test_real_mysql_duplicate_constraints_and_unknown_key_mapping(): void
    {
        $first = User::factory()->create(['username' => 'Exact.First', 'email' => 'Case@Example.test', 'phone' => '00012345678']);
        $second = User::factory()->create();
        DB::table('users')->where('id', $first->id)->update(['avatar_key' => str_repeat('a', 32).'.png']);
        $mapper = app(UserConstraintErrors::class);
        foreach (['username' => $first->username, 'email' => 'case@example.test', 'phone' => $first->phone,
            'avatar_key' => str_repeat('a', 32).'.png'] as $field => $value) {
            try {
                DB::transaction(fn () => DB::table('users')->where('id', $second->id)->update([$field => $value]), 1);
                $this->fail('Expected actual unique constraint.');
            } catch (QueryException $exception) {
                $this->assertSame(1062, (int) $exception->errorInfo[1]);
                $this->assertSame(0, DB::transactionLevel());
                $mapped = $mapper->translate($exception);
                if ($field === 'avatar_key') {
                    $this->assertSame($exception, $mapped);
                } else {
                    $this->assertInstanceOf(ValidationException::class, $mapped);
                    $this->assertSame([$field => [__('identity.user_duplicate')]], $mapped->errors());
                    $this->assertStringNotContainsString($value, json_encode($mapped->errors()));
                }
            }
        }
        $this->assertSame($second->username, $second->fresh()->username);
        $this->assertNull($second->fresh()->email);
        $this->assertNull($second->fresh()->phone);
        $this->assertNull($second->fresh()->avatar_key);
    }

    public function test_real_restrict_fk_requires_reviewed_constraint_name(): void
    {
        $target = User::factory()->create();
        Schema::create('fixture_user_links', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id', 'fixture_user_protected_fk')->references('id')->on('users')->restrictOnDelete();
        });
        try {
            DB::table('fixture_user_links')->insert(['user_id' => $target->id]);
            try {
                DB::transaction(fn () => DB::table('users')->where('id', $target->id)->delete(), 1);
                $this->fail('Expected actual RESTRICT constraint.');
            } catch (QueryException $exception) {
                $this->assertSame(1451, (int) $exception->errorInfo[1]);
                $this->assertSame(0, DB::transactionLevel());
                $mapper = app(UserConstraintErrors::class);
                $this->assertSame($exception, $mapper->translate($exception));
                $mapped = $mapper->translate($exception, ['fixture_user_protected_fk']);
                $this->assertInstanceOf(BusinessException::class, $mapped);
                $this->assertSame(409, $mapped->httpStatus);
                $this->assertSame('IDENTITY_USER_LINKED', $mapped->errorCode);
            }
            $this->assertNotNull($target->fresh());
            $this->assertSame(1, DB::table('fixture_user_links')->count());
        } finally {
            Schema::drop('fixture_user_links');
        }
    }
}
