<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InvestmentPlan extends Model
{
    use BelongsToUser, HasFactory;

    protected $fillable = [
        'user_id',
        'investment_id',
        'account_id',
        'amount',
        'currency_id',
        'frequency',
        'next_run_date',
        'is_active',
    ];

    protected $casts = [
        'next_run_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function investment(): BelongsTo
    {
        return $this->belongsTo(Investment::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}
