<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB; // TENTO RIADOK TU MUSÍ BYŤ
use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InvestmentTransaction extends Model
{
    use HasFactory;
    use BelongsToUser;

    protected $fillable = [
        'user_id',
        'investment_id',
        'type',
        'quantity',
        'price_per_unit',
        'commission',
        'currency_id',
        'exchange_rate',
        'transaction_date',
        'investment_plan_id',
        'notes',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'quantity' => 'string',
        'type' => TransactionType::class,
        'price_per_unit' => 'string',
        'commission' => 'string',
        'exchange_rate' => 'string',
    ];

    public function investment(): BelongsTo
    {
        return $this->belongsTo(Investment::class);
    }
    public function currency(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function investmentPlan(): BelongsTo
    {
        return $this->belongsTo(InvestmentPlan::class);
    }
}
