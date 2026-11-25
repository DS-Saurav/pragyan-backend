<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PublicResilienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_error_templates_are_safe_and_not_indexable(): void
    {
        $pages = [
            'errors.403' => 'This page is not available to you',
            'errors.404' => 'We could not find that page',
            'errors.419' => 'This page needs to be refreshed',
            'errors.429' => 'Please wait a moment',
            'errors.500' => 'We could not complete that request',
        ];

        foreach ($pages as $view => $heading) {
            $markup = view($view)->render();

            $this->assertStringContainsString($heading, $markup);
            $this->assertStringContainsString('noindex, nofollow, noarchive', $markup);
            $this->assertStringContainsString('href="/"', $markup);
            $this->assertStringNotContainsString('javascript:', strtolower($markup));
            $this->assertStringNotContainsString('storage/logs', $markup);
        }
    }

    public function test_not_found_html_response_uses_the_safe_public_error_page(): void
    {
        $this->get('/this-public-address-does-not-exist')
            ->assertNotFound()
            ->assertSee('We could not find that page')
            ->assertSee('noindex, nofollow, noarchive', false)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->assertDontSee('NotFoundHttpException');
    }

    public function test_standard_html_http_errors_render_their_safe_views(): void
    {
        $errors = [
            '/__phase5a-errors/forbidden' => [403, 'This page is not available to you'],
            '/__phase5a-errors/expired' => [419, 'This page needs to be refreshed'],
            '/__phase5a-errors/rate-limited' => [429, 'Please wait a moment'],
            '/__phase5a-errors/unavailable' => [500, 'We could not complete that request'],
        ];

        foreach ($errors as $path => [$status, $heading]) {
            Route::get($path, static function () use ($status): void {
                abort($status);
            });

            $this->get($path)
                ->assertStatus($status)
                ->assertSee($heading)
                ->assertSee('noindex, nofollow, noarchive', false)
                ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
                ->assertDontSee('storage/logs');
        }
    }

    public function test_json_errors_remain_json_and_receive_the_security_baseline(): void
    {
        $response = $this->getJson('/this-api-style-address-does-not-exist');

        $response
            ->assertNotFound()
            ->assertJsonStructure(['message'])
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Permissions-Policy', 'camera=(), geolocation=(), microphone=(), payment=(), usb=()');

        $this->assertFalse($response->headers->has('Content-Security-Policy'));
        $this->assertFalse($response->headers->has('X-Robots-Tag'));
    }

    public function test_public_responses_receive_compatibility_safe_security_headers(): void
    {
        $response = $this->get(route('home'));

        $response
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Permissions-Policy', 'camera=(), geolocation=(), microphone=(), payment=(), usb=()');

        $this->assertFalse($response->headers->has('Content-Security-Policy'));
    }
}
