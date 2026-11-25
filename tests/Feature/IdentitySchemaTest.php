<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\Identity\Models\Permission;
use Modules\Identity\Models\Role;
use Modules\Identity\Models\User;
use Modules\Identity\Services\InitialUserData;
use PHPUnit\Framework\Attributes\DataProvider;

class IdentitySchemaTest extends IdentityDatabaseTestCase
{
    public function test_schema_and_canonical_models(): void
    {
        $this->assertSame(User::class, config('auth.providers.users.model'));
        $this->assertSame(Role::class, config('permission.models.role'));
        $this->assertSame(Permission::class, config('permission.models.permission'));
        $this->assertFalse(config('permission.teams'));
        $this->assertSame('web', config('auth.defaults.guard'));
        $this->assertSame(['id', 'name', 'username', 'email', 'phone', 'password', 'is_active', 'created_at', 'updated_at'], Schema::getColumnListing('users'));

        $columns = DB::table('information_schema.columns')
            ->whereRaw('table_schema = DATABASE()')
            ->where('table_name', 'users')
            ->whereIn('column_name', ['username', 'email', 'phone'])
            ->selectRaw('COLUMN_NAME AS field_name, COLLATION_NAME AS field_collation')
            ->get()
            ->keyBy('field_name');

        $this->assertSame('ascii_bin', $columns['username']->field_collation);
        $this->assertSame('utf8mb4_0900_as_ci', $columns['email']->field_collation);
        $this->assertSame('ascii_bin', $columns['phone']->field_collation);
        $staff = User::factory()->create();
        $this->assertTrue($staff->is_active);
        $this->assertArrayNotHasKey('password', $staff->toArray());
        $this->assertTrue(Hash::check('test-fixture-only-password', $staff->password));
    }

    public function test_username_case_variants_and_nullable_contacts(): void
    {
        $first = User::factory()->create(['username' => 'Mohamed.Admin', 'is_active' => false]);
        $second = User::factory()->create(['username' => 'mohamed.admin']);

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame($first->id, User::where('username', 'Mohamed.Admin')->sole()->id);
        $this->assertSame($second->id, User::where('username', 'mohamed.admin')->sole()->id);
        $this->assertSame(2, User::whereNull('email')->whereNull('phone')->count());

        $this->assertDuplicate('users_username_unique', fn () => User::factory()->create(['username' => 'Mohamed.Admin']));
    }

    public function test_email_case_insensitive_unique_and_phone_preservation(): void
    {
        $user = User::factory()->create([
            'email' => 'Ahmed@Gmail.com',
            'phone' => '00012345678',
            'is_active' => false,
        ])->fresh();

        $this->assertSame('Ahmed@Gmail.com', $user->email);
        $this->assertSame('00012345678', $user->phone);
        $this->assertDuplicate('users_email_unique', fn () => User::factory()->create(['email' => 'ahmed@gmail.com']));
        $this->assertDuplicate('users_phone_unique', fn () => User::factory()->create(['phone' => '00012345678']));
    }

    public function test_native_single_role_constraint_and_replacement(): void
    {
        $first = Role::create(['name' => 'first', 'alias_name' => 'First', 'guard_name' => 'web']);
        $second = Role::create(['name' => 'second', 'alias_name' => 'Second', 'guard_name' => 'web']);
        $staff = User::factory()->create();
        $staff->syncRoles([$first]);

        $this->assertDuplicate('identity_single_role_unique', fn () => DB::table('model_has_roles')->insert([
            'role_id' => $second->id,
            'model_type' => $staff->getMorphClass(),
            'model_id' => $staff->id,
        ]));

        $this->assertSame(1, $staff->roles()->count());
    }

    public function test_initial_data_uses_argon2id_and_preserves_input(): void
    {
        $password = str_repeat('p', 72).'long-tail';
        $data = app(InitialUserData::class)->prepare([
            'name' => 'Test Staff',
            'username' => 'Staff.A',
            'email' => '',
            'phone' => '',
            'password' => $password,
            'password_confirmation' => $password,
        ]);

        $this->assertSame('Staff.A', $data['username']);
        $this->assertNull($data['email']);
        $this->assertNull($data['phone']);
        $this->assertTrue(Hash::check($password, $data['password']));
        $this->assertFalse(Hash::check(str_repeat('p', 72).'other-tail', $data['password']));
        $this->assertStringStartsWith('$argon2id$', $data['password']);
        $this->assertArrayNotHasKey('password_confirmation', $data);
    }

    #[DataProvider('invalidValues')]
    public function test_invalid_input_is_not_silently_normalized(string $field, mixed $value): void
    {
        $input = [
            'name' => 'Test Staff',
            'username' => 'Staff.A',
            'email' => null,
            'phone' => null,
            'password' => 'test-password',
            'password_confirmation' => 'test-password',
        ];
        $input[$field] = $value;
        $this->expectException(ValidationException::class);
        app(InitialUserData::class)->prepare($input);
    }

    public static function invalidValues(): array
    {
        return [
            ['username', 'ab'],
            ['username', ' Staff.A'],
            ['username', 'Staff.A '],
            ['username', "Staff.A\n"],
            ['username', 'Staff@A'],
            ['username', str_repeat('a', 51)],
            ['phone', '0101234567'],
            ['phone', '010123456789'],
            ['phone', ' 01012345678'],
            ['phone', '01012345678 '],
            ['phone', ' '],
            ['phone', '+201012345678'],
            ['phone', '٠١٠١٢٣٤٥٦٧٨'],
            ['email', 'invalid'],
            ['password', 'short'],
            ['password_confirmation', 'different-password'],
        ];
    }

    private function assertDuplicate(string $index, callable $operation): void
    {
        try {
            $operation();
            $this->fail('Expected a MySQL unique-constraint violation.');
        } catch (QueryException $exception) {
            $this->assertSame(1062, (int) $exception->errorInfo[1]);
            $this->assertStringContainsString($index, (string) $exception->errorInfo[2]);
        }
    }
}
