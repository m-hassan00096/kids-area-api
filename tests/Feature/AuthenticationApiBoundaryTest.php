<?php

namespace Tests\Feature;

use App\Support\ApiResponse;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AuthenticationApiBoundaryTest extends AuthenticationHttpTestCase
{
    public function test_json_errors_and_request_correlation_even_without_json_accept(): void
    {
        $server = $this->server();
        $browser = $this->browser($server);
        $options = ['headers' => ['Accept' => 'text/html', 'X-Request-ID' => 'spoofed-client-id']];
        $missing = $browser->request('GET', '/api/v1/nonexistent', options: $options);
        $this->assertSame(404, $missing->status);
        $this->assertSame(['success' => false, 'message' => __('api.errors.not_found'), 'errors' => []], $missing->json());
        $this->assertTrue(Str::isUuid($missing->header('X-Request-ID')));
        $this->assertNotSame('spoofed-client-id', $missing->header('X-Request-ID'));

        $method = $browser->request('GET', '/api/v1/auth/login', options: $options);
        $this->assertSame(405, $method->status);
        $this->assertSame(['success' => false, 'message' => __('api.errors.method_not_allowed'), 'errors' => []], $method->json());
        $this->assertStringContainsString('POST', (string) $method->header('Allow'));
        $this->assertTrue(Str::isUuid($method->header('X-Request-ID')));
        $this->assertNotSame($missing->header('X-Request-ID'), $method->header('X-Request-ID'));

        $unauthenticated = $browser->me($options);
        $this->assertSame(401, $unauthenticated->status);
        $this->assertSame(['success' => false, 'message' => __('api.errors.unauthenticated'), 'errors' => []], $unauthenticated->json());
    }

    public function test_missing_translation_has_safe_fallback_and_bounded_warning(): void
    {
        $warnings = [];
        Log::listen(function (MessageLogged $event) use (&$warnings): void {
            if ($event->message === 'Missing API translation.') {
                $warnings[] = $event->context;
            }
        });
        $response = ApiResponse::error('identity.nonexistent_test_message', 409, ['code' => 'SYNTHETIC_CONFLICT']);
        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame([
            'success' => false,
            'message' => 'تعذر إتمام العملية. يرجى التواصل مع المسؤول.',
            'errors' => ['code' => 'SYNTHETIC_CONFLICT'],
        ], $response->getData(true));
        ApiResponse::text('identity.another_nonexistent_test_message');
        $this->assertCount(1, $warnings);
    }

    public function test_http_503_is_reported_once_with_request_id_while_403_is_not(): void
    {
        $browser = $this->browser($this->server());
        foreach ([503, 403] as $status) {
            $response = $browser->request('GET', '/api/v1/__auth-review-probe', options: ['headers' => [
                'X-Identity-Test-Mode' => 'http-'.$status,
                'X-Identity-Test-Inspect' => '1',
                'Accept' => 'text/html',
            ]]);
            $this->assertSame($status, $response->status);
            $this->assertSame('1', $response->header('X-Test-Redacted'));
            $this->assertSame($status === 503 ? '1' : '0', $response->header('X-Test-Correlated-Report'));
            $this->assertSame($status === 503 ? __('api.errors.unavailable') : __('api.errors.forbidden'), $response->json()['message']);
        }
    }

    public function test_unrelated_api_preserves_query_events_while_native_session_io_remains_private(): void
    {
        $this->user('Staff.Admin');
        $browser = $this->browser($this->server());
        $this->assertSame(200, $browser->login('Staff.Admin')->status);
        $response = $browser->request('GET', '/api/v1/__auth-review-protected-probe', options: ['headers' => [
            'X-Identity-Test-Mode' => 'probe-events',
            'X-Identity-Test-Inspect' => '1',
        ]]);
        $this->assertSame(200, $response->status);
        $this->assertSame('1', $response->header('X-Test-Business-Query-Visible'));
        $this->assertSame('0', $response->header('X-Test-Native-Session-Query-Visible'));
        $this->assertSame('1', $response->header('X-Test-Redacted'));
        $this->assertSame(200, $browser->me()->status);
    }
}
