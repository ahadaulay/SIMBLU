<?php

namespace Tests\Unit;

use App\Models\ReportingCycle;
use App\Models\ReportingPeriod;
use PHPUnit\Framework\TestCase;

class ReportingPeriodLabelTest extends TestCase
{
    public function test_label_follows_the_annual_cycle_frequency(): void
    {
        $monthly = new ReportingPeriod(['year' => 2026, 'quarter' => 2]);
        $monthly->setRelation('cycle', new ReportingCycle(['frequency' => 'MONTHLY']));

        $quarterly = new ReportingPeriod(['year' => 2026, 'quarter' => 2]);
        $quarterly->setRelation('cycle', new ReportingCycle(['frequency' => 'QUARTERLY']));

        $semesterly = new ReportingPeriod(['year' => 2026, 'quarter' => 2]);
        $semesterly->setRelation('cycle', new ReportingCycle(['frequency' => 'SEMESTERLY']));

        $this->assertSame('Februari 2026', $monthly->label);
        $this->assertSame('TW 2 / 2026', $quarterly->label);
        $this->assertSame('Semester 2 / 2026', $semesterly->label);
    }
}
