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
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Investment extends Model
{
    use BelongsToUser, HasFactory;

    protected $fillable = [
        'user_id', 'account_id', 'investment_category_id', 'currency_id',
        'ticker', 'name', 'broker', 'sector', 'industry', 'country', 'asset_type',
        'current_price', 'is_archived', 'last_price_update',
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

    protected array $statsCache = [];

    /**
     * CENTRÁLNY MOZOG VÝPOČTOV
     * Táto metóda zavolá službu a výsledok si zapamätá počas jedného načítania stránky.
     */
    public function getInvestmentStats(): array
    {
        if (isset($this->statsCache[$this->id])) {
            return $this->statsCache[$this->id];
        }

        // Služba vráti: current_quantity, average_buy_price, realized_gain_base, total_invested_base, total_sales_base
        return $this->statsCache[$this->id] = InvestmentCalculationService::getStats($this);
    }

    public function clearStatsCache(): void
    {
        $this->statsCache = [];
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

    protected function averageBuyPriceEur(): Attribute
    {
        return Attribute::make(
            get: function () {
                $qty = BigDecimal::of($this->total_quantity);
                if ($qty->isZero()) return '0.0000';
                
                return (string) BigDecimal::of($this->total_invested_eur)
                    ->dividedBy($qty, 4, RoundingMode::HALF_UP);
            }
        );
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
            get: fn () => (string) $this->transactions->where('type', TransactionType::BUY)->reduce(function ($carry, $tx) {
                $carry = $carry ?? BigDecimal::of(0);
                $costBase = BigDecimal::of($tx->quantity)->multipliedBy($tx->price_per_unit)->plus($tx->commission ?? 0);
                $costEur = CurrencyService::convertToEur((string)$costBase, $tx->currency_id, $tx->exchange_rate);
                return $carry->plus($costEur);
            }, BigDecimal::of(0))
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
            get: fn () => (string) $this->transactions->where('type', TransactionType::SELL)->reduce(function ($carry, $tx) {
                $carry = $carry ?? BigDecimal::of(0);
                $revBase = BigDecimal::of($tx->quantity)->multipliedBy($tx->price_per_unit)->minus($tx->commission ?? 0);
                $revEur = CurrencyService::convertToEur((string)$revBase, $tx->currency_id, $tx->exchange_rate);
                return $carry->plus($revEur);
            }, BigDecimal::of(0))
        );
    }

    // --- UNIVERZÁLNE METÓDY PRE PREPOČTY (Zohľadňujú celkový zisk vrátane meny) ---

    public function getCurrentValueForCurrency(?string $code = null): string
    {
        if (!$code || $code === $this->currency?->code) {
            return $this->is_archived ? $this->total_sales_base : $this->current_market_value_base;
        }
        
        if ($code === 'EUR') {
            return $this->is_archived ? $this->total_sales_eur : $this->current_market_value_eur;
        }

        // Pre ostatné meny prepočítame základnú hodnotu (USD) do cieľovej (CZK...)
        $targetCurrency = Currency::where('code', $code)->first();
        return CurrencyService::convert(
            $this->is_archived ? $this->total_sales_base : $this->current_market_value_base,
            $this->currency_id,
            $targetCurrency?->id
        );
    }

    public function getInvestedForCurrency(?string $code = null): string
    {
        if (!$code || $code === $this->currency?->code) {
            return $this->total_invested_base;
        }

        if ($code === 'EUR') {
            return $this->total_invested_eur;
        }

        // Pre ostatné (CZK) prepočítame EUR základ do cieľovej meny aktuálnym kurzom
        // (Najlepšia aproximácia historických nákladov pre iné meny)
        $targetCurrency = Currency::where('code', $code)->first();
        return CurrencyService::convert($this->total_invested_eur, null, $targetCurrency?->id);
    }

    public function getGainForCurrency(?string $code = null): string
    {
        $current = BigDecimal::of($this->getCurrentValueForCurrency($code));
        $invested = BigDecimal::of($this->getInvestedForCurrency($code));
        return (string) $current->minus($invested);
    }

    protected function gainEur(): Attribute
    {
        return Attribute::make(get: fn() => $this->getGainForCurrency('EUR'));
    }

    // --- DAŇOVÝ TEST ---
    protected function taxFreeQuantity(): Attribute
    {
        return Attribute::make(
            get: function () {
                $oneYearAgo = Carbon::now()->subYear()->startOfDay();
                $lots = $this->getInvestmentStats()['remaining_lots'] ?? [];
                $taxFreeAmount = BigDecimal::of(0);

                foreach ($lots as $lot) {
                    // Ak je nákup starší alebo rovný 1 roku (vrátane výročného dňa)
                    if ($lot['date']->startOfDay()->lessThanOrEqualTo($oneYearAgo)) {
                        $taxFreeAmount = $taxFreeAmount->plus($lot['qty']);
                    }
                }
                return (string) $taxFreeAmount;
            }
        );
    }

    protected function taxFreePercent(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (BigDecimal::of($this->total_quantity)->isZero()) return 0;
                return (float) BigDecimal::of($this->tax_free_quantity)
                    ->dividedBy($this->total_quantity, 4, RoundingMode::HALF_UP)
                    ->multipliedBy(100)
                    ->toFloat();
            }
        );
    }

    protected function taxStatus(): Attribute
    {
        return Attribute::make(
            get: function () {
                $percent = $this->tax_free_percent;
                if ($percent >= 100) return 'Oslobodené';
                if ($percent <= 0) return 'Zdaniteľné';
                return "Časť oslobodená ({$percent}%)";
            }
        );
    }

    protected function nextTaxFreeDate(): Attribute
    {
        return Attribute::make(
            get: function () {
                $oneYearAgo = Carbon::now()->subYear()->startOfDay();
                $lots = $this->getInvestmentStats()['remaining_lots'] ?? [];

                foreach ($lots as $lot) {
                    if ($lot['date']->startOfDay()->greaterThan($oneYearAgo)) {
                        return $lot['date']->copy()->addYear();
                    }
                }
                return null;
            }
        );
    }

}