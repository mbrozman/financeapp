<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Services\CurrencyService;
use App\Services\InvestmentCalculationService;
use App\Enums\TransactionType;
use Carbon\Carbon;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class Investment extends Model
{
    use BelongsToUser;

    protected $fillable = [
        'user_id', 'account_id', 'investment_category_id', 'currency_id',
        'ticker', 'name', 'broker', 'current_price', 'is_archived', 'last_price_update',
    ];

    protected $casts = [
        'is_archived'        => 'boolean',
        'current_price'      => 'string', 
        'last_price_update'  => 'datetime',
    ];

    // --- VZŤAHY ---
    public function account(): BelongsTo { return $this->belongsTo(Account::class); }
    public function currency(): BelongsTo { return $this->belongsTo(Currency::class); }
    public function category(): BelongsTo { return $this->belongsTo(InvestmentCategory::class, 'investment_category_id'); }
    public function transactions(): HasMany { return $this->hasMany(InvestmentTransaction::class); }
    public function priceHistories(): HasMany { return $this->hasMany(InvestmentPriceHistory::class); }

    /**
     * CENTRÁLNY MOZOG VÝPOČTOV
     * Táto metóda zavolá službu a výsledok si zapamätá počas jedného načítania stránky.
     */
    protected function getInvestmentStats(): array
    {
        static $statsCache = [];
        if (isset($statsCache[$this->id])) return $statsCache[$this->id];

        // Služba vráti: current_quantity, average_buy_price, realized_gain_base, total_invested_base, total_sales_base
        return $statsCache[$this->id] = InvestmentCalculationService::getStats($this);
    }

    // --- VÝPOČTY ODVODENÉ ZO SLUŽBY (Domovská mena) ---

    protected function totalQuantity(): Attribute
    {
        return Attribute::make(get: fn() => $this->getInvestmentStats()['current_quantity']);
    }

    protected function averageBuyPriceBase(): Attribute
    {
        return Attribute::make(get: fn() => $this->getInvestmentStats()['average_buy_price']);
    }

    protected function totalInvestedBase(): Attribute
    {
        return Attribute::make(get: fn() => $this->getInvestmentStats()['total_invested_base']);
    }

    protected function totalSalesBase(): Attribute
    {
        return Attribute::make(get: fn() => $this->getInvestmentStats()['total_sales_base']);
    }

    protected function realizedGainBase(): Attribute
    {
        return Attribute::make(get: fn() => $this->getInvestmentStats()['realized_gain_base']);
    }

    // --- OSTATNÉ VÝPOČTY (Stále v modeli kvôli jednoduchosti) ---

    protected function currentMarketValueBase(): Attribute
    {
        return Attribute::make(
            get: fn() => (string) BigDecimal::of($this->total_quantity)->multipliedBy($this->current_price ?? 0)
        );
    }

    protected function totalGainBase(): Attribute
    {
        return Attribute::make(
            get: function () {
                $invested = BigDecimal::of($this->total_invested_base);
                // Ak je archivované, porovnávame tržby, inak trhovú hodnotu
                $current = $this->is_archived 
                    ? BigDecimal::of($this->total_sales_base) 
                    : BigDecimal::of($this->current_market_value_base);
                
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
                return (string) $gain->dividedBy($invested, 4, RoundingMode::HALF_UP)->multipliedBy(100)->toScale(2, RoundingMode::HALF_UP);
            }
        );
    }

    // --- VÝPOČTY V EUR (Cez CurrencyService) ---

    protected function totalInvestedEur(): Attribute
    {
        return Attribute::make(
            get: fn () => (string) $this->transactions->where('type', TransactionType::BUY)->sum(function ($tx) {
                $costBase = BigDecimal::of($tx->quantity)->multipliedBy($tx->price_per_unit)->plus($tx->commission ?? 0);
                return CurrencyService::convertToEur((string)$costBase, $tx->currency_id, $tx->exchange_rate);
            })
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
            get: fn () => (string) $this->transactions->where('type', TransactionType::SELL)->sum(function ($tx) {
                $revBase = BigDecimal::of($tx->quantity)->multipliedBy($tx->price_per_unit)->minus($tx->commission ?? 0);
                return CurrencyService::convertToEur((string)$revBase, $tx->currency_id, $tx->exchange_rate);
            })
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

    // --- DAŇOVÝ TEST ---
    protected function taxFreeQuantity(): Attribute
    {
        return Attribute::make(
            get: function () {
                $oneYearAgo = Carbon::now()->subYear();
                $processedSold = BigDecimal::of($this->transactions->where('type', TransactionType::SELL)->sum('quantity'));
                $buys = $this->transactions->where('type', TransactionType::BUY)->sortBy('transaction_date');
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