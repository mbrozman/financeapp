<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetDefinition extends Model
{
    use BelongsToUser;

    protected $fillable = ['user_id', 'category_id', 'financial_plan_item_id', 'amount', 'valid_from'];

    protected $casts = [
        'valid_from' => 'date',
        'amount' => 'string',
    ];

    public function category(): BelongsTo { return $this->belongsTo(Category::class); }
    public function planItem(): BelongsTo { return $this->belongsTo(FinancialPlanItem::class, 'financial_plan_item_id'); }
}