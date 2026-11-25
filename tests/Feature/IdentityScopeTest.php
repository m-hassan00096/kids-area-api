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
        $this->assertTrue(class_exists(Sanctum::class));
        $this->assertFalse(Schema::hasTable('audit_logs'));
        $this->assertFalse(Schema::hasTable('identity_access_guard'));
        $this->assertTrue(Schema::hasTable('sessions'));
        $this->assertTrue(Schema::hasTable('identity_login_throttles'));
        $this->assertTrue(Schema::hasColumn('users', 'auth_version'));
        $this->assertFalse(Schema::hasTable('personal_access_tokens'));
        $this->assertFalse(Schema::hasTable('password_reset_tokens'));
        $this->assertFalse(Schema::hasTable('jobs'));
        $this->assertFalse(Schema::hasColumn('users', 'deleted_at'));
        $this->assertFalse(Schema::hasColumn('users', 'role_id'));
        $this->assertFileDoesNotExist(app_path('Models/User.php'));

        $approvedAuthRoutes = [
            'sanctum/csrf-cookie' => ['GET', 'HEAD'],
            'api/v1/auth/login' => ['POST'],
            'api/v1/auth/logout' => ['POST'],
            'api/v1/auth/me' => ['GET', 'HEAD'],
            'api/v1/profile/password' => ['PUT'],
        ];
        foreach (Route::getRoutes() as $route) {
            if (isset($approvedAuthRoutes[$route->uri()])) {
                $this->assertSame($approvedAuthRoutes[$route->uri()], $route->methods());
                unset($approvedAuthRoutes[$route->uri()]);

                continue;
            }
            $this->assertDoesNotMatchRegularExpression('#^(?:api/v1/)?(?:auth|login|logout|profile|users|roles|selects|audit|sanctum)(?:/|$)#', $route->uri());
        }
        // Route registration is completed in the Authentication implementation.
        $this->assertSame([], $approvedAuthRoutes);
    }
}
