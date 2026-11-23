<?php

namespace Tests\Unit;

use App\Support\LandUse;
use PHPUnit\Framework\TestCase;

class LandUseTest extends TestCase
{
    public function test_the_land_use_comes_from_the_indexing_record_even_when_misspelt(): void
    {
        $this->assertSame('RES', LandUse::code('RESIDENTIAL'));
        $this->assertSame('RES', LandUse::code('RESIDENCIAL'));
        $this->assertSame('RES', LandUse::code('RESDENTIAL'));
        $this->assertSame('COM', LandUse::code('COM'));
        $this->assertSame('AGR', LandUse::code('AGRICULTURAL'));
        $this->assertSame('GOV', LandUse::code('GOVERNMENT'));
    }

    public function test_the_file_number_code_is_used_when_the_record_has_no_land_use(): void
    {
        $this->assertSame('RES', LandUse::code(null, 'RES-2016-452'));
        $this->assertSame('RES', LandUse::code('', 'CON-RES-2008-169'));
        $this->assertSame('AGR', LandUse::code(null, 'CON-AG-1981-28'));
        $this->assertSame('COM', LandUse::code('DCIV', 'CON-COM-2019-12'));
        $this->assertNull(LandUse::code(null, 'MLKN 3010'));
    }

    public function test_a_residential_plan_on_a_residential_file_matches_and_anything_else_is_flagged(): void
    {
        $this->assertSame(LandUse::MATCH, LandUse::check('Residential', 'RES')[0]);
        $this->assertSame(LandUse::MISMATCH, LandUse::check('Commercial', 'RES')[0]);
        $this->assertSame(LandUse::NOT_CHECKED, LandUse::check('Other', 'RES')[0]);
        $this->assertSame(LandUse::NOT_CHECKED, LandUse::check('Residential', null)[0]);
    }
}
