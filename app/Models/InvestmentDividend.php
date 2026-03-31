<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvestmentDividend extends Model
{
    use HasFactory;
    use HasUuids;
    use BelongsToUser;

    protected $fillable = [
        'user_id',
        'investment_id',
        'amount',
        'currency_id',
        'exchange_rate',
        'payout_date',
        'add_to_broker_balance',
        'notes',
    ];

    protected $casts = [
        'payout_date' => 'date',
        'amount' => 'string',
        'exchange_rate' => 'string',
        'add_to_broker_balance' => 'boolean',
    ];

    public function investment(): BelongsTo
    {
        return $this->belongsTo(Investment::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}
