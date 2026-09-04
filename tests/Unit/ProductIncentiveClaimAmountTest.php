<?php

namespace Tests\Unit;

use App\Models\ProductIncentive;
use PHPUnit\Framework\TestCase;

class ProductIncentiveClaimAmountTest extends TestCase
{
    public function test_takes_lower_of_rate_on_fob_and_cap_per_pcs(): void
    {
        $incentive = new ProductIncentive([
            'scheme'    => 'drawback',
            'percent_1' => 10,
            'cap_value' => 2,
        ]);

        // 10% of FOB 100 = 10; cap 2 × 3 pcs = 6 → lower is 6
        $this->assertSame(6.0, $incentive->claimAmount(100, 3));

        // 10% of FOB 100 = 10; cap 2 × 10 pcs = 20 → lower is 10
        $this->assertSame(10.0, $incentive->claimAmount(100, 10));
    }

    public function test_rosctl_adds_both_percents_when_only_one_cap(): void
    {
        $incentive = new ProductIncentive([
            'scheme'    => 'rosctl',
            'percent_1' => 1.5,
            'percent_2' => 0.5,
            'cap_value' => 100,
        ]);

        // Legacy: 2% of 200 = 4; single cap unused
        $this->assertSame(4.0, $incentive->claimAmount(200, 1));
    }

    public function test_rosctl_with_two_caps_sums_each_leg(): void
    {
        $incentive = new ProductIncentive([
            'scheme'      => 'rosctl',
            'percent_1'   => 2.65,
            'percent_2'   => 2.10,
            'cap_value'   => 59.2,
            'cap_value_2' => 40.0,
        ]);

        // FOB 10000, 100 PCS:
        // leg1: min(265, 5920) = 265
        // leg2: min(210, 4000) = 210
        // total 475
        $this->assertSame(475.0, $incentive->claimAmount(10000, 100));
    }

    public function test_missing_cap_uses_rate_only(): void
    {
        $incentive = new ProductIncentive([
            'scheme'    => 'rodtep',
            'percent_1' => 5,
            'cap_value' => null,
        ]);

        $this->assertSame(5.0, $incentive->claimAmount(100, 99));
    }

    public function test_breakdown_exposes_both_sides(): void
    {
        $incentive = new ProductIncentive([
            'scheme'    => 'drawback',
            'percent_1' => 10,
            'cap_value' => 2,
        ]);

        $b = $incentive->claimBreakdown(100, 3);

        $this->assertSame(10.0, $b['rate_amount']);
        $this->assertSame(6.0, $b['cap_amount']);
        $this->assertSame(6.0, $b['claim']);
    }
}
