<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Services\CurrencyService;
use Carbon\Carbon;

class Investment extends Model
{
    use BelongsToUser;

    protected $fillable = [
        'user_id',
        'account_id',
        'investment_category_id',
        'currency_id',
        'ticker',
        'name',
        'broker',
        'current_price', // Toto zostáva, sťahujeme to z API
        'is_archived',
        'last_price_update'
    ];

    protected $casts = [
        'is_archived' => 'boolean',
        'current_price' => 'decimal:4',
        'last_price_update' => 'datetime',
    ];

    // --- VZŤAHY ---

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
    public function category(): BelongsTo
    {
        return $this->belongsTo(InvestmentCategory::class, 'investment_category_id');
    }
    public function transactions(): HasMany
    {
        return $this->hasMany(InvestmentTransaction::class);
    }

    public function priceHistories(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(InvestmentPriceHistory::class);
    }
    // --- VÝPOČTY MNOŽSTVA ---

    protected function totalQuantity(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (!$this->relationLoaded('transactions') && !$this->transactions()->exists()) {
                    return 0;
                }
                $buys = $this->transactions->where('type', 'buy')->sum('quantity');
                $sells = $this->transactions->where('type', 'sell')->sum('quantity');
                return (float)($buys - $sells);
            }
        );
    }

    // --- VÝPOČTY V DOMOVSKEJ MENE (napr. USD) ---

    protected function totalInvestedBase(): Attribute
    {
        return Attribute::make(
            get: function () {
                $buys = $this->transactions->where('type', 'buy');
                return $buys->sum(fn($tx) => ($tx->quantity * $tx->price_per_unit) + $tx->commission);
            }
        );
    }

    protected function totalSalesBase(): Attribute
    {
        return Attribute::make(
            get: function () {
                $sells = $this->transactions->where('type', 'sell');
                return $sells->sum(fn($tx) => ($tx->quantity * $tx->price_per_unit) - $tx->commission);
            }
        );
    }

    protected function currentMarketValueBase(): Attribute
    {
        return Attribute::make(
            get: fn() => (float)$this->total_quantity * (float)$this->current_price
        );
    }

    /**
 * AKTUÁLNA HODNOTA EUR (Dnešná realita)
 * Berie aktuálnu cenu a dnešný kurz z DB
 */
protected function currentMarketValueEur(): Attribute
{
    return Attribute::make(
        get: function () {
            if ($this->is_archived) return (float)$this->total_sales_eur;
            
            $valueBase = (float)$this->total_quantity * (float)$this->current_price;
            return CurrencyService::convertToEur($valueBase, $this->currency_id);
        }
    );
}

    protected function averageBuyPriceBase(): Attribute
    {
        return Attribute::make(
            get: function () {
                $qty = $this->transactions->where('type', 'buy')->sum('quantity');
                return $qty > 0 ? $this->total_invested_base / $qty : 0;
            }
        );
    }

  /**
 * INVESTOVANÉ EUR (Historická realita)
 * Berie nákupy a ich vtedajšie kurzy
 */
protected function totalInvestedEur(): Attribute
{
    return Attribute::make(
        get: fn () => $this->transactions->where('type', 'buy')->sum(function($tx) {
            $costBase = ($tx->quantity * $tx->price_per_unit) + $tx->commission;
            return CurrencyService::convertToEur($costBase, $tx->currency_id, $tx->exchange_rate);
        })
    );
}

    // Tržby z predaja (EUR) - používa HISTORICKÉ kurzy
    protected function totalSalesEur(): Attribute
    {
        return Attribute::make(
            get: function () {
                $sells = $this->transactions->where('type', 'sell');
                return $sells->sum(function ($tx) {
                    $revenueBase = ($tx->quantity * $tx->price_per_unit) - $tx->commission;
                    return CurrencyService::convertToEur($revenueBase, $this->currency?->code ?? 'USD', $tx->exchange_rate);
                });
            }
        );
    }

  /**
 * ZISK EUR (Čistý rozdiel)
 */
protected function gainEur(): Attribute
{
    return Attribute::make(
        get: fn () => (float)$this->current_market_value_eur - (float)$this->total_invested_eur
    );
}

    protected function taxFreeQuantity(): Attribute
    {
        return Attribute::make(
            get: function () {
                $oneYearAgo = Carbon::now()->subYear();
                $totalSold = $this->transactions->where('type', 'sell')->sum('quantity');
                $buys = $this->transactions->where('type', 'buy')->sortBy('transaction_date');

                $taxFreeAmount = 0;
                $processedSold = $totalSold;

                foreach ($buys as $buy) {
                    $qty = (float) $buy->quantity;
                    if ($processedSold > 0) {
                        $subtract = min($qty, $processedSold);
                        $qty -= $subtract;
                        $processedSold -= $subtract;
                    }
                    if ($qty > 0 && $buy->transaction_date->lessThan($oneYearAgo)) {
                        $taxFreeAmount += $qty;
                    }
                }
                return $taxFreeAmount;
            }
        );
    }
}
