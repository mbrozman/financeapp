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
        'current_price',
        'is_archived',
        'last_price_update',
    ];

    protected $casts = [
        'is_archived'        => 'boolean',
        'current_price'      => 'decimal:4',
        'last_price_update'  => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // VZŤAHY
    // -------------------------------------------------------------------------

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

    public function priceHistories(): HasMany
    {
        return $this->hasMany(InvestmentPriceHistory::class);
    }

    // -------------------------------------------------------------------------
    // VÝPOČTY MNOŽSTVA
    // -------------------------------------------------------------------------

    /**
     * Celkové aktuálne množstvo (nákupy − predaje).
     *
     * BUG (opravený): Pôvodná podmienka
     *   !$this->relationLoaded('transactions') && !$this->transactions()->exists()
     * bola logicky nekonzistentná – keď relácia nebola načítaná ALE záznamy
     * existovali, kód prešiel do výpočtu a spustil lazy-load. Keď relácia
     * nebola načítaná a záznamy neexistovali, vrátil 0 bez toho, aby dal šancu
     * lazy-loadu. Správne riešenie: nechaj Laravel lazy-load urobiť svoju prácu
     * a skontroluj len prázdnosť kolekcie.
     */
    protected function totalQuantity(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->transactions->isEmpty()) {
                    return 0;
                }

                $buys  = $this->transactions->where('type', 'buy')->sum('quantity');
                $sells = $this->transactions->where('type', 'sell')->sum('quantity');

                return (float) ($buys - $sells);
            }
        );
    }

    // -------------------------------------------------------------------------
    // VÝPOČTY V DOMOVSKEJ MENE (napr. USD)
    // -------------------------------------------------------------------------

    /**
     * Celková investovaná suma v domovskej mene (vrátane poplatkov).
     */
    protected function totalInvestedBase(): Attribute
    {
        return Attribute::make(
            get: function () {
                return $this->transactions
                    ->where('type', 'buy')
                    ->sum(fn ($tx) => ($tx->quantity * $tx->price_per_unit) + $tx->commission);
            }
        );
    }

    /**
     * Celkové tržby z predaja v domovskej mene (po odpočítaní poplatkov).
     */
    protected function totalSalesBase(): Attribute
    {
        return Attribute::make(
            get: function () {
                return $this->transactions
                    ->where('type', 'sell')
                    ->sum(fn ($tx) => ($tx->quantity * $tx->price_per_unit) - $tx->commission);
            }
        );
    }

    /**
     * Aktuálna trhová hodnota v domovskej mene.
     */
    protected function currentMarketValueBase(): Attribute
    {
        return Attribute::make(
            get: fn () => (float) $this->total_quantity * (float) $this->current_price
        );
    }

    /**
     * Celkový zisk v domovskej mene.
     * Pre archivované investície = tržby − investované.
     * Pre aktívne investície    = trhová hodnota − investované.
     */
    protected function totalGainBase(): Attribute
    {
        return Attribute::make(
            get: function () {
                $investedBase = (float) $this->total_invested_base;
                $currentBase  = $this->is_archived
                    ? (float) $this->total_sales_base
                    : (float) $this->current_market_value_base;

                return $currentBase - $investedBase;
            }
        );
    }

    /**
     * Celkový výnos v % (počítaný z domovskej meny).
     * Najpresnejší ukazovateľ úspešnosti výberu aktíva.
     */
    protected function totalGainPercent(): Attribute
    {
        return Attribute::make(
            get: function () {
                $investedBase = (float) $this->total_invested_base;

                if ($investedBase <= 0) {
                    return 0;
                }

                return ($this->total_gain_base / $investedBase) * 100;
            }
        );
    }

    /**
 * Priemerná nákupná cena v mene aktíva (USD/EUR...)
 * Používa sa v grafe ako vodorovná čiara (Break-even).
 */
protected function averageBuyPriceBase(): Attribute
{
    return Attribute::make(
        get: function () {
            // Použijeme sum() priamo na kolekcii transakcií v pamäti
            $buyTransactions = $this->transactions->where('type', 'buy');
            
            if ($buyTransactions->isEmpty()) return 0;

            $totalQuantity = $buyTransactions->sum('quantity');
            
            if ($totalQuantity <= 0) return 0;

            // Vypočítame priemer: (Suma cien + Poplatky) / Kusy
            $totalCostBase = $buyTransactions->sum(fn ($tx) => ($tx->quantity * $tx->price_per_unit) + $tx->commission);

            return (float) ($totalCostBase / $totalQuantity);
        }
    );
}
    // -------------------------------------------------------------------------
    // VÝPOČTY V EUR
    // -------------------------------------------------------------------------

    /**
     * Celková investovaná suma v EUR (historické kurzy z každej transakcie).
     */
    protected function totalInvestedEur(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->transactions
                ->where('type', 'buy')
                ->sum(function ($tx) {
                    $costBase = ($tx->quantity * $tx->price_per_unit) + $tx->commission;

                    return CurrencyService::convertToEur($costBase, $tx->currency_id, $tx->exchange_rate);
                })
        );
    }

    /**
     * Celkové tržby z predaja v EUR (historické kurzy z každej transakcie).
     *
     * BUG (opravený): Pôvodný kód používal $this->currency?->code ?? 'USD'
     * (currency investície) namiesto $tx->currency_id (currency transakcie).
     * To spôsobovalo nesprávny prevod, ak mala transakcia inú menu ako
     * investícia, alebo ak currency relácia nebola načítaná (N+1 + chybné ID).
     */
    protected function totalSalesEur(): Attribute
    {
        return Attribute::make(
            get: function () {
                return $this->transactions
                    ->where('type', 'sell')
                    ->sum(function ($tx) {
                        $revenueBase = ($tx->quantity * $tx->price_per_unit) - $tx->commission;

                        return CurrencyService::convertToEur($revenueBase, $tx->currency_id, $tx->exchange_rate);
                    });
            }
        );
    }

    /**
     * Aktuálna trhová hodnota v EUR (dnešný kurz z DB).
     * Pre archivované investície vráti total_sales_eur.
     */
    protected function currentMarketValueEur(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->is_archived) {
                    return (float) $this->total_sales_eur;
                }

                $valueBase = (float) $this->total_quantity * (float) $this->current_price;

                return CurrencyService::convertToEur($valueBase, $this->currency_id);
            }
        );
    }

    /**
     * Celkový zisk v EUR.
     * Pre archivované investície = total_sales_eur − total_invested_eur.
     * Pre aktívne investície    = current_market_value_eur − total_invested_eur.
     *
     * BUG (opravený): Pôvodný kód obsahoval duplicitný atribút gain_eur, ktorý
     * robil ten istý výpočet BEZ zohľadnenia is_archived. Ponechaný je iba
     * total_gain_eur, ktorý správne ošetruje oba stavy.
     */
    protected function totalGainEur(): Attribute
    {
        return Attribute::make(
            get: function () {
                $invested = (float) $this->total_invested_eur;
                $current  = $this->is_archived
                    ? (float) $this->total_sales_eur
                    : (float) $this->current_market_value_eur;

                return $current - $invested;
            }
        );
    }

    // -------------------------------------------------------------------------
    // DAŇOVÉ VÝPOČTY
    // -------------------------------------------------------------------------

    /**
     * Množstvo akcií držaných dlhšie ako 1 rok (daňovo oslobodené – FIFO).
     */
    protected function taxFreeQuantity(): Attribute
    {
        return Attribute::make(
            get: function () {
                $oneYearAgo     = Carbon::now()->subYear();
                $processedSold  = (float) $this->transactions->where('type', 'sell')->sum('quantity');
                $buys           = $this->transactions->where('type', 'buy')->sortBy('transaction_date');
                $taxFreeAmount  = 0.0;

                foreach ($buys as $buy) {
                    $qty = (float) $buy->quantity;

                    // Odpočítaj predané kusy v poradí FIFO
                    if ($processedSold > 0) {
                        $subtract       = min($qty, $processedSold);
                        $qty           -= $subtract;
                        $processedSold -= $subtract;
                    }

                    // Zvyšné množstvo z tohto nákupu je daňovo oslobodené,
                    // ak bol nákup starší ako 1 rok.
                    if ($qty > 0 && $buy->transaction_date->lessThan($oneYearAgo)) {
                        $taxFreeAmount += $qty;
                    }
                }

                return $taxFreeAmount;
            }
        );
    }
}