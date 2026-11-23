<?php

namespace Tests\Feature;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class FoundationConfigurationTest extends TestCase
{
    public function test_bootstrap_locale_and_timezones(): void
    {
        $this->assertInstanceOf(Application::class, app());
        $this->assertSame('ar', config('app.locale'));
        $this->assertSame('ar', config('app.fallback_locale'));
        $this->assertSame('UTC', config('app.timezone'));
        $this->assertSame('Africa/Cairo', config('operations.business_timezone'));
    }

    public function test_arabic_system_translation_resolves(): void
    {
        $this->assertSame('النظام جاهز.', __('foundation.ready'));
    }

    public function test_operating_timezone_can_change_without_mutating_event_timezone(): void
    {
        config()->set('operations.business_timezone', 'Asia/Riyadh');

        $this->assertSame('Asia/Riyadh', config('operations.business_timezone'));
        $this->assertSame('UTC', config('app.timezone'));
    }

    public function test_api_prefix_is_registered_once_for_test_scoped_probe(): void
    {
        $this->getJson('/api/v1/__foundation-route-probe')
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->getJson('/api/v1/api/v1/__foundation-route-probe')->assertNotFound();

        $route = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($item) => $item->uri() === 'api/v1/__foundation-route-probe');

        $this->assertNotNull($route);
    }
}
