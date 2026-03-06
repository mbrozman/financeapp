<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\BelongsToUser; 
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialPlan extends Model
{
    use BelongsToUser;
    protected $fillable = ['user_id','monthly_income', 'expected_annual_return', 'is_active'];

    public function items(): HasMany
    {
        return $this->hasMany(FinancialPlanItem::class);
    }

    /**
     * Vypočíta, koľko EUR mesačne ide do "sporivej" časti majetku
     */
    public function getMonthlySavingsAmount(): \Brick\Math\BigDecimal
    {
        $savingsPercentage = $this->items()
            ->where('contributes_to_net_worth', true)
            ->sum('percentage');

        return \Brick\Math\BigDecimal::of($this->monthly_income)
            ->multipliedBy($savingsPercentage)
            ->dividedBy(100, 4, \Brick\Math\RoundingMode::HALF_UP);
    }
}