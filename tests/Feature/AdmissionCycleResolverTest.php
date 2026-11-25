<?php

namespace Tests\Feature;

use App\Models\AdmissionCycle;
use App\Services\PublicAdmissions\AdmissionCycleResolver;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdmissionCycleResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_a_published_cycle_inside_its_kathmandu_display_window(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-04-14 00:15:00', 'Asia/Kathmandu'));
        $cycle = AdmissionCycle::factory()->published()->create([
            'label' => 'Academic Year 2083',
            'display_starts_on' => '2026-04-14',
            'display_ends_on' => '2026-04-15',
        ]);

        $resolved = app(AdmissionCycleResolver::class)->current();

        $this->assertSame($cycle->id, $resolved?->id);

        $this->travelTo(CarbonImmutable::parse('2026-04-16 00:01:00', 'Asia/Kathmandu'));

        $this->assertSame(null, app(AdmissionCycleResolver::class)->current());
    }

    public function test_closed_cycle_stays_visible_through_its_explicit_display_end(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-05-10 23:55:00', 'Asia/Kathmandu'));
        $cycle = AdmissionCycle::factory()->published()->create([
            'public_status' => 'closed',
            'display_starts_on' => '2026-05-01',
            'display_ends_on' => '2026-05-10',
        ]);

        $this->assertSame($cycle->id, app(AdmissionCycleResolver::class)->current()?->id);

        $this->travelTo(CarbonImmutable::parse('2026-05-11 00:01:00', 'Asia/Kathmandu'));

        $this->assertSame(null, app(AdmissionCycleResolver::class)->current());
    }

    public function test_draft_withdrawn_and_archived_cycles_never_resolve_publicly(): void
    {
        foreach (['draft', 'withdrawn', 'archived'] as $state) {
            AdmissionCycle::factory()->create([
                'label' => ucfirst($state).' cycle',
                'publication_state' => $state,
                'published_at' => $state === 'draft' ? null : now('Asia/Kathmandu'),
            ]);
        }

        $this->assertSame(null, app(AdmissionCycleResolver::class)->current());
    }

    public function test_resolver_uses_a_deterministic_latest_window_when_legacy_records_overlap(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-06-05 10:00:00', 'Asia/Kathmandu'));
        AdmissionCycle::factory()->published()->create([
            'label' => 'Earlier malformed cycle',
            'display_starts_on' => '2026-06-01',
            'display_ends_on' => '2026-06-30',
            'published_at' => '2026-06-01 08:00:00',
        ]);
        $expected = AdmissionCycle::factory()->published()->create([
            'label' => 'Later malformed cycle',
            'display_starts_on' => '2026-06-03',
            'display_ends_on' => '2026-06-30',
            'published_at' => '2026-06-03 08:00:00',
        ]);

        $resolved = app(AdmissionCycleResolver::class)->current();

        $this->assertSame($expected->id, $resolved?->id);
    }
}
