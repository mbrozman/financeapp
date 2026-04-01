<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB; // TENTO RIADOK TU MUSÍ BYŤ
use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Cache;
use App\Services\DashboardFinanceService;

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
        'subtract_from_broker',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'quantity' => 'string',
        'type' => TransactionType::class,
        'price_per_unit' => 'string',
        'commission' => 'string',
        'exchange_rate' => 'string',
        'subtract_from_broker' => 'boolean',
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

    protected static function booted(): void
    {
        static::created(fn (InvestmentTransaction $tx) => $tx->clearDashboardCache());
        static::deleted(fn (InvestmentTransaction $tx) => $tx->clearDashboardCache());
        static::updated(fn (InvestmentTransaction $tx) => $tx->clearDashboardCache());
    }

    public function clearDashboardCache(): void
    {
        $year = $this->transaction_date?->year ?? now()->year;
        Cache::forget(DashboardFinanceService::getYearlyCashflowCacheKey($this->user_id, $year));
    }
}
