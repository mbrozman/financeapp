<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvestmentPlanItem extends Model
{
    protected $fillable = [
        'investment_plan_id',
        'investment_id',
        'weight',
    ];

    protected $casts = [
        'weight' => 'decimal:2',
    ];

    public function investmentPlan(): BelongsTo
    {
        return $this->belongsTo(InvestmentPlan::class);
    }

    public function investment(): BelongsTo
    {
        return $this->belongsTo(Investment::class);
    }
}
