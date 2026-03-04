<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Services\CurrencyService;
use Carbon\Carbon;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

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
        'current_price',
        'is_archived',
        'last_price_update',
    ];

    protected $casts = [
        'is_archived'        => 'boolean',
        'current_price'      => 'string', // BigDecimal preferuje stringy
        'last_price_update'  => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // VZŤAHY
    // -------------------------------------------------------------------------

    public function account(): BelongsTo { return $this->belongsTo(Account::class); }
    public function currency(): BelongsTo { return $this->belongsTo(Currency::class); }
    public function category(): BelongsTo { return $this->belongsTo(InvestmentCategory::class, 'investment_category_id'); }
    public function transactions(): HasMany { return $this->hasMany(InvestmentTransaction::class); }
    public function priceHistories(): HasMany { return $this->hasMany(InvestmentPriceHistory::class); }

    // -------------------------------------------------------------------------
    // VÝPOČTY MNOŽSTVA (BigDecimal)
    // -------------------------------------------------------------------------

    protected function totalQuantity(): Attribute
    {
        return Attribute::make(
            get: function () {
                $total = BigDecimal::of(0);
                foreach ($this->transactions as $tx) {
                    $qty = BigDecimal::of($tx->quantity);
                    $total = ($tx->type === 'buy') ? $total->plus($qty) : $total->minus($qty);
                }
                return (string) $total;
            }
        );
    }

    // -------------------------------------------------------------------------
    // VÝPOČTY V DOMOVSKEJ MENE (napr. USD)
    // -------------------------------------------------------------------------

    protected function totalInvestedBase(): Attribute
    {
        return Attribute::make(
            get: function () {
                $total = BigDecimal::of(0);
                foreach ($this->transactions->where('type', 'buy') as $tx) {
                    $cost = BigDecimal::of($tx->quantity)->multipliedBy($tx->price_per_unit)->plus($tx->commission);
                    $total = $total->plus($cost);
                }
                return (string) $total->toScale(4, RoundingMode::HALF_UP);
            }
        );
    }

    protected function totalSalesBase(): Attribute
    {
        return Attribute::make(
            get: function () {
                $total = BigDecimal::of(0);
                foreach ($this->transactions->where('type', 'sell') as $tx) {
                    $revenue = BigDecimal::of($tx->quantity)->multipliedBy($tx->price_per_unit)->minus($tx->commission);
                    $total = $total->plus($revenue);
                }
                return (string) $total->toScale(4, RoundingMode::HALF_UP);
            }
        );
    }

    protected function currentMarketValueBase(): Attribute
    {
        return Attribute::make(
            get: fn () => (string) BigDecimal::of($this->total_quantity)->multipliedBy($this->current_price ?? 0)
        );
    }

    protected function totalGainBase(): Attribute
    {
        return Attribute::make(
            get: function () {
                $invested = BigDecimal::of($this->total_invested_base);
                $current  = $this->is_archived ? BigDecimal::of($this->total_sales_base) : BigDecimal::of($this->current_market_value_base);
                return (string) $current->minus($invested);
            }
        );
    }

    protected function totalGainPercent(): Attribute
    {
        return Attribute::make(
            get: function () {
                $invested = BigDecimal::of($this->total_invested_base);
                if ($invested->isZero()) return '0';
                
                $gain = BigDecimal::of($this->total_gain_base);
                // Výpočet: (Zisk / Investované) * 100
                return (string) $gain->dividedBy($invested, 4, RoundingMode::HALF_UP)->multipliedBy(100)->toScale(2, RoundingMode::HALF_UP);
            }
        );
    }

    protected function averageBuyPriceBase(): Attribute
    {
        return Attribute::make(
            get: function () {
                $qty = BigDecimal::of($this->transactions->where('type', 'buy')->sum('quantity'));
                if ($qty->isZero()) return '0';
                return (string) BigDecimal::of($this->total_invested_base)->dividedBy($qty, 4, RoundingMode::HALF_UP);
            }
        );
    }

    // -------------------------------------------------------------------------
    // VÝPOČTY V EUR (Cez CurrencyService)
    // -------------------------------------------------------------------------

    protected function totalInvestedEur(): Attribute
    {
        return Attribute::make(
            get: function () {
                $total = BigDecimal::of(0);
                foreach ($this->transactions->where('type', 'buy') as $tx) {
                    $costBase = BigDecimal::of($tx->quantity)->multipliedBy($tx->price_per_unit)->plus($tx->commission);
                    $costEur = CurrencyService::convertToEur((string)$costBase, $tx->currency_id, $tx->exchange_rate);
                    $total = $total->plus($costEur);
                }
                return (string) $total;
            }
        );
    }

    protected function currentMarketValueEur(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->is_archived) return $this->total_sales_eur;
                return CurrencyService::convertToEur($this->current_market_value_base, $this->currency_id);
            }
        );
    }

    protected function totalSalesEur(): Attribute
    {
        return Attribute::make(
            get: function () {
                $total = BigDecimal::of(0);
                foreach ($this->transactions->where('type', 'sell') as $tx) {
                    $revBase = BigDecimal::of($tx->quantity)->multipliedBy($tx->price_per_unit)->minus($tx->commission);
                    $revEur = CurrencyService::convertToEur((string)$revBase, $tx->currency_id, $tx->exchange_rate);
                    $total = $total->plus($revEur);
                }
                return (string) $total;
            }
        );
    }

    protected function gainEur(): Attribute
    {
        return Attribute::make(
            get: function () {
                $invested = BigDecimal::of($this->total_invested_eur);
                $current  = $this->is_archived ? BigDecimal::of($this->total_sales_eur) : BigDecimal::of($this->current_market_value_eur);
                return (string) $current->minus($invested);
            }
        );
    }

    // -------------------------------------------------------------------------
    // DAŇOVÉ VÝPOČTY
    // -------------------------------------------------------------------------

    protected function taxFreeQuantity(): Attribute
    {
        return Attribute::make(
            get: function () {
                $oneYearAgo = Carbon::now()->subYear();
                $processedSold = BigDecimal::of($this->transactions->where('type', 'sell')->sum('quantity'));
                $buys = $this->transactions->where('type', 'buy')->sortBy('transaction_date');
                $taxFreeAmount = BigDecimal::of(0);

                foreach ($buys as $buy) {
                    $qty = BigDecimal::of($buy->quantity);

                    if ($processedSold->isGreaterThan(0)) {
                        $subtract = $qty->isLessThanOrEqualTo($processedSold) ? $qty : $processedSold;
                        $qty = $qty->minus($subtract);
                        $processedSold = $processedSold->minus($subtract);
                    }

                    if ($qty->isGreaterThan(0) && $buy->transaction_date->lessThan($oneYearAgo)) {
                        $taxFreeAmount = $taxFreeAmount->plus($qty);
                    }
                }
                return (string) $taxFreeAmount;
            }
        );
    }
}