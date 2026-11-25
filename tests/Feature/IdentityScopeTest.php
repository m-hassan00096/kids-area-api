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

        $approvedRoutes = [
            'sanctum/csrf-cookie' => ['GET', 'HEAD'],
            'api/v1/auth/login' => ['POST'],
            'api/v1/auth/logout' => ['POST'],
            'api/v1/auth/me' => ['GET', 'HEAD'],
            'api/v1/profile/password' => ['PUT'],
            'api/v1/users' => ['GET', 'HEAD', 'POST'],
            'api/v1/users/{user}' => ['GET', 'HEAD', 'PUT', 'DELETE'],
            'api/v1/users/{user}/avatar' => ['GET', 'HEAD'],
            'api/v1/selects' => ['GET', 'HEAD'],
        ];
        foreach (Route::getRoutes() as $route) {
            if (isset($approvedRoutes[$route->uri()])) {
                $this->assertSame([], array_diff($route->methods(), $approvedRoutes[$route->uri()]));
                $approvedRoutes[$route->uri()] = array_values(array_diff($approvedRoutes[$route->uri()], $route->methods()));
                if ($approvedRoutes[$route->uri()] === []) {
                    unset($approvedRoutes[$route->uri()]);
                }

                continue;
            }
            $this->assertDoesNotMatchRegularExpression('#^(?:api/v1/)?(?:auth|login|logout|profile|users|roles|selects|audit|sanctum)(?:/|$)#', $route->uri());
        }
        // Only the approved F01/F02 and F03 inventory may be registered.
        $this->assertSame([], $approvedRoutes);
    }
}
