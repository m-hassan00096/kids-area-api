<?php

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Modules\Identity\Actions\BootstrapManagerAction;
use Modules\Identity\Actions\SeedIdentityCatalogueAction;
use Modules\Identity\Models\Permission;
use Modules\Identity\Models\Role;
use Modules\Identity\Models\User;
use Modules\Identity\Support\PermissionCatalogue;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Support\IdentityPrompts;

class IdentityBootstrapTest extends IdentityDatabaseTestCase
{
    public function test_command_provisions_exactly_one_founder_with_secure_prompts(): void
    {
        app(SeedIdentityCatalogueAction::class)->execute();
        $queries = [];
        DB::listen(function (QueryExecuted $event) use (&$queries): void {
            $queries[] = $event->bindings;
        });
        DB::enableQueryLog();
        $password = 'synthetic-founder-secret';
        $command = $this->prompts($password);
        $tester = new CommandTester($command);
        $this->assertSame(0, $tester->execute([], ['interactive' => true]));

        $founder = User::sole();
        $this->assertSame(1, $founder->id);
        $this->assertTrue($founder->is_active);
        $this->assertSame(['manager'], $founder->roles()->pluck('name')->all());
        $this->assertEqualsCanonicalizing(PermissionCatalogue::keys(), $founder->getAllPermissions()->pluck('name')->all());
        $this->assertSame(0, $founder->permissions()->count());
        $this->assertTrue(Hash::check($password, $founder->password));
        $this->assertNull($founder->email);
        $this->assertNull($founder->phone);
        $this->assertSame([false, false], $command->fallbacks);
        $this->assertStringContainsString(__('identity.bootstrap_success'), $tester->getDisplay());
        $this->assertStringNotContainsString($password, $tester->getDisplay());
        $this->assertStringNotContainsString($founder->password, $tester->getDisplay());
        $this->assertStringNotContainsString($founder->password, json_encode($queries));
        $this->assertStringNotContainsString($founder->password, json_encode(DB::getQueryLog()));
        DB::disableQueryLog();

        $again = new CommandTester($this->prompts($password));
        $this->assertSame(1, $again->execute([], ['interactive' => true]));
        $this->assertStringContainsString(__('identity.users_exist'), $again->getDisplay());
        $this->assertSame(1, User::count());
        $this->assertSame(1, DB::table('model_has_roles')->count());
    }

    public function test_noninteractive_or_unavailable_hidden_input_refuses_without_changes(): void
    {
        app(SeedIdentityCatalogueAction::class)->execute();
        $this->assertSame(1, Artisan::call('identity:bootstrap-manager', ['--no-interaction' => true]));
        $this->assertStringContainsString(__('identity.interactive_required'), Artisan::output());

        $command = $this->prompts('synthetic-secret');
        $command->failSecret = true;
        $tester = new CommandTester($command);
        $this->assertSame(1, $tester->execute([], ['interactive' => true]));
        $this->assertStringNotContainsString('synthetic-secret', $tester->getDisplay());
        $this->assertSame(0, User::count());

        $command = $this->prompts('synthetic-secret');
        $command->answers[5] = 'different-confirmation';
        $tester = new CommandTester($command);
        $this->assertSame(1, $tester->execute([], ['interactive' => true]));
        $this->assertStringContainsString(__('identity.invalid_data'), $tester->getDisplay());
        $this->assertSame(0, User::count());
        $this->assertSame(1, $this->nextId());
    }

    public function test_existing_user_even_inactive_and_nonfounder_refuses_without_mutation(): void
    {
        app(SeedIdentityCatalogueAction::class)->execute();
        User::factory()->create()->delete();
        $existing = User::factory()->create(['is_active' => false]);
        $this->assertNotSame(1, $existing->id);
        $before = $this->state();
        $this->assertRefusal('IDENTITY_USERS_EXIST');
        $this->assertSame($before, $this->state());
    }

    #[DataProvider('badCatalogues')]
    public function test_missing_or_inconsistent_catalogue_never_repairs_or_creates_user(string $problem): void
    {
        if ($problem !== 'unseeded') {
            app(SeedIdentityCatalogueAction::class)->execute();
            $manager = Role::findByName('manager', 'web');
            $reception = Role::findByName('reception', 'web');
            $permission = Permission::where('name', 'identity.users.view')->sole();

            match ($problem) {
                'manager-missing' => $manager->delete(),
                'reception-missing' => $reception->delete(),
                'manager-inactive' => $manager->update(['is_active' => false]),
                'reception-inactive' => $reception->update(['is_active' => false]),
                'role-wrong-guard' => $reception->update(['guard_name' => 'other']),
                'permission-missing' => $permission->delete(),
                'permission-wrong-guard' => $permission->update(['guard_name' => 'other']),
                'marker-missing' => DB::table('identity_feature_provisions')->where('feature_key', 'activities.catalogue.v1')->delete(),
                'grant-missing' => $manager->revokePermissionTo($permission),
            };
        }

        $before = $this->state();
        $this->assertRefusal('IDENTITY_SEED_REQUIRED');
        $this->assertSame($before, $this->state());
        $this->assertSame(1, $this->nextId());
    }

    public static function badCatalogues(): array
    {
        return array_map(fn ($problem) => [$problem], [
            'unseeded', 'manager-missing', 'reception-missing', 'manager-inactive',
            'reception-inactive', 'role-wrong-guard', 'permission-missing',
            'permission-wrong-guard', 'grant-missing', 'marker-missing',
        ]);
    }

    public function test_advanced_sequence_is_not_reset_even_when_users_empty(): void
    {
        app(SeedIdentityCatalogueAction::class)->execute();
        User::factory()->create()->delete();
        $next = $this->nextId();
        $expiry = DB::selectOne('SELECT @@SESSION.information_schema_stats_expiry AS value')->value;
        $this->assertGreaterThan(1, $next);
        $before = $this->state();
        $this->assertRefusal('IDENTITY_FOUNDER_ID_UNAVAILABLE');
        $this->assertSame($before, $this->state());
        $this->assertSame($next, $this->nextId());
        $this->assertSame($expiry, DB::selectOne('SELECT @@SESSION.information_schema_stats_expiry AS value')->value);
    }

    #[DataProvider('failurePoints')]
    public function test_bootstrap_rolls_back_rows_and_refuses_retry_after_consumed_id(string $point): void
    {
        app(SeedIdentityCatalogueAction::class)->execute();
        $before = $this->state();
        $armed = true;

        if ($point === 'after-user') {
            User::created(function () use (&$armed): void {
                if ($armed) {
                    $armed = false;
                    throw new RuntimeException('Synthetic post-insert failure');
                }
            });
        } else {
            DB::connection()->beforeExecuting(function (string $query) use (&$armed): void {
                if ($armed && str_contains(strtolower($query), 'insert into '.chr(96).'model_has_roles'.chr(96))) {
                    $armed = false;
                    throw new RuntimeException('Synthetic pivot failure');
                }
            });
        }

        try {
            app(BootstrapManagerAction::class)->execute($this->credentials());
            $this->fail('Synthetic failure must propagate out of the transaction.');
        } catch (RuntimeException $exception) {
            $this->assertStringStartsWith('Synthetic', $exception->getMessage());
        }

        $this->assertSame($before, $this->state());
        $this->assertGreaterThan(1, $this->nextId());
        $this->assertRefusal('IDENTITY_FOUNDER_ID_UNAVAILABLE');
        $this->assertSame($before, $this->state());
    }

    public static function failurePoints(): array
    {
        return [['after-user'], ['during-pivot']];
    }

    public function test_unexpected_command_failure_never_logs_credentials_or_raw_exception(): void
    {
        app(SeedIdentityCatalogueAction::class)->execute();
        $password = 'synthetic-log-redaction-password';
        $capturedHash = null;
        $messages = [];

        Log::listen(function (MessageLogged $event) use (&$messages): void {
            $messages[] = [$event->message, $event->context];
        });

        User::created(function (User $user) use ($password, &$capturedHash): void {
            $capturedHash = $user->password;
            throw new RuntimeException('Synthetic exception containing '.$password.' '.$capturedHash);
        });

        $tester = new CommandTester($this->prompts($password));
        $this->assertSame(1, $tester->execute([], ['interactive' => true]));
        $this->assertStringContainsString(__('identity.bootstrap_failed'), $tester->getDisplay());
        $this->assertNotNull($capturedHash);
        $this->assertNotEmpty($messages);
        $this->assertStringNotContainsString($password, $tester->getDisplay());
        $this->assertStringNotContainsString($capturedHash, $tester->getDisplay());
        $log = json_encode($messages, JSON_THROW_ON_ERROR);
        $this->assertStringContainsString('Identity founder provisioning failed.', $log);
        $this->assertStringNotContainsString($password, $log);
        $this->assertStringNotContainsString($capturedHash, $log);
        $this->assertStringNotContainsString('Synthetic exception containing', $log);
        $this->assertSame(0, User::count());
        $this->assertSame(0, DB::table('model_has_roles')->count());
    }

    public function test_unavailable_sequence_metadata_refuses_before_insert_and_restores_setting(): void
    {
        app(SeedIdentityCatalogueAction::class)->execute();
        $before = $this->state();
        $expiry = DB::selectOne('SELECT @@SESSION.information_schema_stats_expiry AS value')->value;
        $armed = true;

        DB::connection()->beforeExecuting(function (string $query) use (&$armed): void {
            if ($armed && str_contains(strtolower($query), 'from information_schema.tables')) {
                $armed = false;
                throw new RuntimeException('Synthetic inaccessible sequence metadata');
            }
        });

        $this->assertRefusal('IDENTITY_FOUNDER_ID_UNAVAILABLE');
        $this->assertSame($before, $this->state());
        $this->assertSame(1, $this->nextId());
        $this->assertSame($expiry, DB::selectOne('SELECT @@SESSION.information_schema_stats_expiry AS value')->value);
    }

    private function prompts(string $password): IdentityPrompts
    {
        $command = new IdentityPrompts;
        $command->setLaravel(app());
        $command->answers = ['Founder', 'Founder.A', '', '', $password, $password];

        return $command;
    }

    private function credentials(): array
    {
        return [
            'name' => 'Founder',
            'username' => 'Founder.A',
            'email' => null,
            'phone' => null,
            'password' => 'synthetic-founder-secret',
            'password_confirmation' => 'synthetic-founder-secret',
        ];
    }

    private function assertRefusal(string $code): void
    {
        try {
            app(BootstrapManagerAction::class)->execute($this->credentials());
            $this->fail('Expected bootstrap refusal.');
        } catch (BusinessException $exception) {
            $this->assertSame($code, $exception->errorCode);
        }
    }

    private function state(): array
    {
        $state = [];
        foreach (['users', 'roles', 'permissions', 'model_has_roles', 'model_has_permissions', 'role_has_permissions', 'identity_feature_provisions'] as $table) {
            $state[$table] = DB::table($table)->get()->map(fn ($row) => (array) $row)->all();
        }

        return $state;
    }

    private function nextId(): int
    {
        $expiry = (int) DB::selectOne('SELECT @@SESSION.information_schema_stats_expiry AS value')->value;
        DB::statement('SET SESSION information_schema_stats_expiry = 0');

        try {
            return (int) DB::selectOne("SELECT AUTO_INCREMENT AS value FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'users'")->value;
        } finally {
            DB::statement('SET SESSION information_schema_stats_expiry = '.$expiry);
        }
    }
}
