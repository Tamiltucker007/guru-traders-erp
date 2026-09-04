<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductIncentive extends Model
{
    /**
     * The three export incentive schemes on the Product Master sheet.
     * Adding a fourth is one entry here plus one enum value in the migration.
     */
    public const SCHEMES = [
        'drawback' => 'Drawback',
        'rosctl'   => 'RoSCTL',
        'rodtep'   => 'RoDTEP',
    ];

    /**
     * Only RoSCTL is quoted as two percentages (and two caps) on the sheet.
     * The form hides percent_2 / cap_value_2 for the other schemes.
     */
    public const TWO_PERCENT_SCHEMES = ['rosctl'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'product_id',
        'scheme',
        'percent_1',
        'percent_2',
        'cap_value',
        'cap_value_2',
        'calculation_basis_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'percent_1'   => 'decimal:3',
            'percent_2'   => 'decimal:3',
            'cap_value'   => 'decimal:4',
            'cap_value_2' => 'decimal:4',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function calculationBasis(): BelongsTo
    {
        return $this->belongsTo(CalculationBasis::class);
    }

    public function schemeLabel(): string
    {
        return self::SCHEMES[$this->scheme] ?? $this->scheme;
    }

    /**
     * Claim amount for a shipment line.
     *
     * Drawback / RoDTEP (and legacy RoSCTL with one cap):
     *   min( (p1+p2)% × FOB , cap × PCS )
     *
     * RoSCTL with two caps (sheet cols U and Y):
     *   min(p1% × FOB, cap1 × PCS) + min(p2% × FOB, cap2 × PCS)
     */
    public function claimAmount(float $fobValue, float $pcsQty): float
    {
        return $this->claimBreakdown($fobValue, $pcsQty)['claim'];
    }

    /**
     * @return array{rate_amount: float, cap_amount: float|null, claim: float, rate_percent: float}
     */
    public function claimBreakdown(float $fobValue, float $pcsQty): array
    {
        if ($this->usesPairedCaps()) {
            $leg1 = $this->legBreakdown($fobValue, $pcsQty, (float) ($this->percent_1 ?? 0), $this->cap_value);
            $leg2 = $this->legBreakdown($fobValue, $pcsQty, (float) ($this->percent_2 ?? 0), $this->cap_value_2);

            return [
                'rate_percent' => $leg1['rate_percent'] + $leg2['rate_percent'],
                'rate_amount'  => round($leg1['rate_amount'] + $leg2['rate_amount'], 4),
                'cap_amount'   => round(($leg1['cap_amount'] ?? 0) + ($leg2['cap_amount'] ?? 0), 4),
                'claim'        => round($leg1['claim'] + $leg2['claim'], 4),
            ];
        }

        return $this->legBreakdown(
            $fobValue,
            $pcsQty,
            (float) ($this->percent_1 ?? 0) + (float) ($this->percent_2 ?? 0),
            $this->cap_value
        );
    }

    /**
     * RoSCTL sheet has two independent %/cap pairs once both caps are present.
     */
    public function usesPairedCaps(): bool
    {
        return $this->scheme === 'rosctl'
            && $this->percent_2 !== null && $this->percent_2 !== ''
            && $this->cap_value_2 !== null && $this->cap_value_2 !== '';
    }

    /**
     * @return array{rate_amount: float, cap_amount: float|null, claim: float, rate_percent: float}
     */
    private function legBreakdown(float $fobValue, float $pcsQty, float $ratePercent, mixed $capValue): array
    {
        $rateAmount = round(max(0, $fobValue * ($ratePercent / 100)), 4);

        $hasCap = $capValue !== null && $capValue !== '';
        $capAmount = $hasCap
            ? round(max(0, $pcsQty * (float) $capValue), 4)
            : null;

        $claim = $capAmount === null
            ? $rateAmount
            : round(min($rateAmount, $capAmount), 4);

        return [
            'rate_percent' => $ratePercent,
            'rate_amount'  => $rateAmount,
            'cap_amount'   => $capAmount,
            'claim'        => $claim,
        ];
    }
}
