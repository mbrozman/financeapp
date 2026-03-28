<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Budget;
use Brick\Math\BigDecimal;

class FinancialPlanItem extends Model
{
    protected $fillable = [
        'financial_plan_id', 
        'name', 
        'color',
        'percentage', 
        'applies_expected_return', 
        'contributes_to_net_worth', 
        'is_reserve', 
        'is_saving'
    ];

    public static function getBaseColors(): array
    {
        return [
            '#ff0000' => 'Červená (Výdavky)',
            '#87ceeb' => 'Modrá (Investície)',
            '#228b22' => 'Zelená (Rezerva)',
            '#ffbf00' => 'Žltá (Vreckové)',
            '#232323' => 'Čierna (Ostatné)',
            '#a855f7' => 'Fialová',
            '#78350f' => 'Hnedá',
            '#ffffff' => 'Biela',
        ];
    }

    protected $casts = [
        'contributes_to_net_worth' => 'boolean',
        'applies_expected_return' => 'boolean',
        'is_reserve' => 'boolean',
        'is_saving' => 'boolean',
    ];

    /**
     * Reserve target is now a manual input in the parent FinancialPlan.
     */
    public function getReserveTargetAmount(): float
    {
        return (float) ($this->financialPlan?->reserve_target ?? 0);
    }

    public function financialPlan(): BelongsTo
    {
        return $this->belongsTo(FinancialPlan::class);
    }

    /**
     * Vypočíta, koľko EUR z tohto šuflíka je už v danom mesiaci ALOKOVANÝCH do rozpočtov.
     */
    public function getCommittedAmount(string $period): BigDecimal
    {
        // Sčítame všetky limity rozpočtov priradených k tomuto šuflíku v danom mesiaci
        $sum = Budget::where('financial_plan_item_id', $this->id)
            ->where('period', $period)
            ->sum('limit_amount');

        return BigDecimal::of($sum ?? 0);
    }
}
