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
        'current_price', 'is_archived', 'is_benchmark', 'last_price_update',
        'total_quantity', 'average_buy_price', 'average_buy_price_eur',
        'total_invested_base', 'total_invested_eur',
        'total_sales_base', 'total_sales_eur',
        'total_dividends_base', 'realized_gain_base',
        'total_dividends_eur', 'realized_gain_eur', 'notes',
    ];

    protected $casts = [
        'is_archived'        => 'boolean',
        'is_benchmark'       => 'boolean',
        'current_price'      => 'string', 
        'last_price_update'  => 'datetime',
    ];

    // --- VZŤAHY ---
    public function account(): BelongsTo { return $this->belongsTo(Account::class); }
    public function currency(): BelongsTo { return $this->belongsTo(Currency::class); }
    public function category(): BelongsTo { return $this->belongsTo(InvestmentCategory::class, 'investment_category_id'); }
    public function transactions(): HasMany { return $this->hasMany(InvestmentTransaction::class); }
    public function dividends(): HasMany { return $this->hasMany(InvestmentDividend::class); }
    public function priceHistories(): HasMany { return $this->hasMany(InvestmentPriceHistory::class); }

    /**
     * Vylúči benchmark záznamy z bežných dotazov (používatelia ich nesmú vidieť v zoznamoch)
     */
    protected static function booted(): void
    {
        // 1. Vylúči benchmarky (SPY, QQQ) z bežných pohľadov
        static::addGlobalScope('non_benchmark', function ($q) {
            $q->where('is_benchmark', false);
        });

        // 2. Vylúči archivované (predané) investície (všade v appke)
        static::addGlobalScope('active_only', function ($q) {
            $q->where('is_archived', false);
        });
    }

    /**
     * Scope pre prístup výlučne k benchmark záznamom (napr. v grafoch)
     */
    public static function benchmarks(): \Illuminate\Database\Eloquent\Builder
    {
        return static::withoutGlobalScopes()->where('is_benchmark', true);
    }

    protected array $statsCache = [];

    /**
     * CENTRÁLNY MOZOG VÝPOČTOV
     * Poznámka: Snažíme sa mu vyhýbať a používať denormalizované stĺpce.
     */
    public function getInvestmentStats(): array
    {
        if (isset($this->statsCache[$this->id])) {
            return $this->statsCache[$this->id];
        }

        return $this->statsCache[$this->id] = InvestmentCalculationService::getStats($this);
    }

    public function clearStatsCache(): void
    {
        $this->statsCache = [];
    }

    // --- VÝPOČTY ODVODENÉ Z DATABÁZY (Rýchle accessory) ---
    // Atribúty sa načítavajú priamo zo stĺpcov tabuľky:
    // total_quantity, average_buy_price, total_invested_base, atď.

    protected function unrealizedGainBase(): Attribute
    {
        return Attribute::make(
            get: function () {
                $qty = BigDecimal::of($this->total_quantity);
                if ($qty->isZero()) return '0';
                
                $price = BigDecimal::of($this->current_price ?? '0');
                $avgBuyPrice = BigDecimal::of($this->average_buy_price ?? '0');
                
                // Unrealized Gain = (Current Price - Avg Buy Price) * Quantity
                return (string) $price->minus($avgBuyPrice)->multipliedBy($qty);
            }
        );
    }

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
                $invested = BigDecimal::of($this->total_invested_base ?? '0');
                $sales = BigDecimal::of($this->total_sales_base ?? '0');
                $dividends = BigDecimal::of($this->total_dividends_base ?? '0');
                $currentValue = $this->is_archived 
                    ? BigDecimal::zero() 
                    : BigDecimal::of($this->current_market_value_base ?? '0');
                
                return (string) $currentValue->plus($sales)->plus($dividends)->minus($invested);
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

    // --- EUR ŠPECIFICKÉ (Dynamické) ---

    protected function currentMarketValueEur(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->is_archived) return $this->total_sales_eur ?? '0';
                return CurrencyService::convertToEur($this->current_market_value_base ?? '0', $this->currency_id);
            }
        );
    }

    protected function gainEur(): Attribute
    {
        return Attribute::make(get: fn() => $this->getGainForCurrency('EUR'));
    }

    // --- UNIVERZÁLNE METÓDY PRE PREPOČTY (Zohľadňujú celkový zisk vrátane meny) ---

    public function getCurrentValueForCurrency(?string $code = null): string
    {
        if (!$code || $code === $this->currency?->code) {
            return $this->is_archived ? $this->total_sales_base : $this->current_market_value_base;
        }

        if ($code === 'EUR') {
            return $this->current_market_value_eur;
        }
        
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

        $targetCurrency = Currency::where('code', $code)->first();
        return CurrencyService::convert($this->total_invested_base, $this->currency_id, $targetCurrency?->id);
    }

    public function getAveragePriceForCurrency(?string $code = null): string
    {
        if (!$code || $code === $this->currency?->code) {
            return $this->average_buy_price;
        }

        if ($code === 'EUR') {
            return $this->average_buy_price_eur;
        }

        $targetCurrency = Currency::where('code', $code)->first();
        return CurrencyService::convert($this->average_buy_price, $this->currency_id, $targetCurrency?->id);
    }

    public function getGainForCurrency(?string $code = null): string
    {
        if (!$code || $code === $this->currency?->code) {
            return $this->total_gain_base;
        }

        if ($code === 'EUR') {
            $invested = BigDecimal::of($this->total_invested_eur ?? '0');
            $sales = BigDecimal::of($this->total_sales_eur ?? '0');
            $dividends = BigDecimal::of($this->total_dividends_eur ?? '0');
            $currentValue = $this->is_archived 
                ? BigDecimal::zero() 
                : BigDecimal::of($this->current_market_value_eur ?? '0');

            return (string) $currentValue->plus($sales)->plus($dividends)->minus($invested);
        }

        $targetCurrency = Currency::where('code', $code)->first();
        return CurrencyService::convert($this->total_gain_base, $this->currency_id, $targetCurrency?->id);
    }

    public function getDividendsForCurrency(?string $code = null): string
    {
        if (!$code || $code === $this->currency?->code) {
            return $this->total_dividends_base ?? '0';
        }

        if ($code === 'EUR') {
            return $this->total_dividends_eur ?? '0';
        }

        $targetCurrency = Currency::where('code', $code)->first();
        return CurrencyService::convert($this->total_dividends_base ?? '0', $this->currency_id, $targetCurrency?->id);
    }

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