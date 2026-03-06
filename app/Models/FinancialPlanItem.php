<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Budget;
use Brick\Math\BigDecimal;

class FinancialPlanItem extends Model
{
    protected $fillable = ['financial_plan_id', 'name', 'percentage', 'applies_expected_return', 'contributes_to_net_worth'];

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
