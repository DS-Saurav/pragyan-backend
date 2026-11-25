<?php

namespace Tests\Feature;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicGetReadOnlyTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_get_routes_do_not_write_a_session_cache_or_content_record(): void
    {
        /** @var SessionManager $sessions */
        $sessions = app('session');
        $sessions->setDefaultDriver('database');
        $sessions->forgetDrivers();

        $cache = app('cache');
        $cache->setDefaultDriver('database');
        $cache->forgetDriver(['array', 'database']);

        $writes = [];
        DB::listen(static function (QueryExecuted $query) use (&$writes): void {
            if (preg_match('/^\s*(?:insert|update|delete|replace|alter|drop|create|truncate)\b/i', $query->sql) === 1) {
                $writes[] = $query->sql;
            }
        });

        foreach ([
            '/',
            '/admissions',
            '/contact',
            '/calendar',
            '/notices',
            '/about',
            '/academics',
            '/team',
            '/news',
            '/gallery',
            '/sitemap.xml',
            '/robots.txt',
            '/ne',
            '/ne/admissions',
            '/ne/contact',
            '/ne/calendar',
            '/ne/notices',
            '/ne/about',
            '/ne/academics',
            '/ne/team',
            '/ne/news',
            '/ne/gallery',
        ] as $uri) {
            $this->withServerVariables(['HTTP_HOST' => 'untrusted-request-host.test'])
                ->get($uri)
                ->assertOk()
                ->assertHeaderMissing('Set-Cookie');
        }

        $this->assertSame([], $writes, 'Public GET routes must not persist sessions, cache entries, or content changes.');
    }
}
