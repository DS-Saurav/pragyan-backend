<?php

namespace Tests\Unit\Helpers;

use Tests\TestCase;

class SystemHelperTest extends TestCase
{
    public function test_academic_year_remains_previous_until_april_fourteenth(): void
    {
        $this->travelTo('2026-04-13 12:00:00');

        $this->assertSame('2082', current_academic_year());
    }

    public function test_academic_year_rolls_over_on_april_fourteenth(): void
    {
        $this->travelTo('2026-04-14 12:00:00');

        $this->assertSame('2083', current_academic_year());
    }

    public function test_academic_year_uses_current_bikram_sambat_year_after_rollover(): void
    {
        $this->travelTo('2026-10-16 12:00:00');

        $this->assertSame('2083', current_academic_year());
    }
}
