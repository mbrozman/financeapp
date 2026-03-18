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
    protected static function booted(): void
    {
        static::created(function (InvestmentTransaction $tx) {
            DB::transaction(function () use ($tx) {
                InvestmentCalculationService::refreshStats($tx->investment);
                self::adjustBrokerBalance($tx, 'apply');
            });
        });

        static::updating(function (InvestmentTransaction $tx) {
            DB::transaction(function () use ($tx) {
                // Odčítame starý stav (rollback) pred uložením nového
                // Použijeme kópiu modelu s pôvodnými dátami
                $oldTx = $tx->replicate();
                $oldTx->setRawAttributes($tx->getOriginal());
                self::adjustBrokerBalance($oldTx, 'rollback');
            });
        });

        static::updated(function (InvestmentTransaction $tx) {
            DB::transaction(function () use ($tx) {
                InvestmentCalculationService::refreshStats($tx->investment);
                self::adjustBrokerBalance($tx, 'apply');
            });
        });

        static::deleted(function (InvestmentTransaction $tx) {
            DB::transaction(function () use ($tx) {
                InvestmentCalculationService::refreshStats($tx->investment);
                self::adjustBrokerBalance($tx, 'rollback');
            });
        });
    }

    private static function adjustBrokerBalance(InvestmentTransaction $tx, string $mode): void
    {
        $account = $tx->investment->account;
        if (!$account) return;

        // Výpočet dopadu transakcie v EUR (všetky účty v tejto appke majú zostatok v EUR)
        // Ak je transakcia v inej mene, prepočítame ju historickým kurzom
        $amountBase = (float)$tx->quantity * (float)$tx->price_per_unit;
        $commBase = (float)$tx->commission;
        
        // Celkový dopad v mene transakcie
        // Buy: - (Cena + Poplatok)
        // Sell: + (Cena - Poplatok)
        // Dividend: + (Cena - Poplatok)
        
        $impactInBase = 0;
        if ($tx->type === TransactionType::BUY) {
            $impactInBase = -($amountBase + $commBase);
        } elseif ($tx->type === TransactionType::SELL || $tx->type === TransactionType::DIVIDEND) {
            $impactInBase = ($amountBase - $commBase);
        }

        // Prepočet na EUR (vždy používame historický kurz transakcie pre zostatok na účte)
        $impactInEur = $impactInBase * (float)$tx->exchange_rate;

        if ($mode === 'rollback') {
            $impactInEur = -$impactInEur;
        }

        // Aktualizácia zostatku (Account balance je uložený ako string/decimal)
        $newBalance = \Brick\Math\BigDecimal::of($account->balance)->plus($impactInEur);
        $account->updateQuietly(['balance' => (string)$newBalance]);
    }
}
