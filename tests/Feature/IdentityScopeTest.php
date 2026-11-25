<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Modules\Identity\Actions\SeedIdentityCatalogueAction;
use Modules\Identity\Models\Permission;
use Modules\Identity\Support\IdentityPermissions;

class IdentityScopeTest extends IdentityDatabaseTestCase
{
    public function test_deferred_features_remain_absent(): void
    {
        app(SeedIdentityCatalogueAction::class)->execute();

        $this->assertEqualsCanonicalizing(IdentityPermissions::keys(), Permission::pluck('name')->all());
        $this->assertFalse(class_exists(Sanctum::class));
        $this->assertFalse(Schema::hasTable('audit_logs'));
        $this->assertFalse(Schema::hasTable('identity_access_guard'));
        $this->assertFalse(Schema::hasTable('sessions'));
        $this->assertFalse(Schema::hasTable('personal_access_tokens'));
        $this->assertFalse(Schema::hasTable('password_reset_tokens'));
        $this->assertFalse(Schema::hasTable('jobs'));
        $this->assertFalse(Schema::hasColumn('users', 'deleted_at'));
        $this->assertFalse(Schema::hasColumn('users', 'role_id'));
        $this->assertFileDoesNotExist(app_path('Models/User.php'));

        foreach (Route::getRoutes() as $route) {
            $this->assertDoesNotMatchRegularExpression('#^(?:api/v1/)?(?:auth|login|logout|profile|users|roles|selects|audit|sanctum)(?:/|$)#', $route->uri());
        }
    }
}
