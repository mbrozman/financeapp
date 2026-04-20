<?php

namespace App\Models;

use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute; // Naimportované správne
use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Cache;
use App\Services\DashboardFinanceService;

class Transaction extends Model
{
    use BelongsToUser, LogsActivity, HasFactory;

    protected $fillable = [
        'user_id',
        'account_id',
        'category_id',
        'amount',
        'transaction_date',
        'description',
        'attachment',
        'type',
        'linked_transaction_id'
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'amount' => 'string',
        'type' => TransactionType::class,
    ];

    // --- VZŤAHY ---

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class)->withTrashed();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function linkedTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'linked_transaction_id');
    }
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable() // Loguj všetky polia, ktoré sú vo $fillable
            ->logOnlyDirty() // Loguj len tie polia, ktoré sa reálne zmenili
            ->dontSubmitEmptyLogs(); // Nevytváraj záznam, ak sa nič nezmenilo
    }

    // --- MODERNÝ SPÔSOB MANIPULÁCIE S HODNOTOU (Laravel 12) ---
    // Táto funkcia zabezpečí, že pred uložením do DB sa suma upraví podľa typu.
    protected function amount(): Attribute
    {
        return Attribute::make(
            set: function ($value, $attributes) {
                // $attributes['type'] môže byť enum objekt alebo string (závisí od poradia fill())
                // Preto extrahujeme vždy čistý string
                $rawType = $attributes['type'] ?? null;

                if ($rawType instanceof \App\Enums\TransactionType) {
                    $typeStr = $rawType->value;
                } elseif ($rawType instanceof \UnitEnum) {
                    $typeStr = $rawType->value ?? (string) $rawType->name;
                } else {
                    $typeStr = (string) ($rawType ?? $this->getRawOriginal('type') ?? 'expense');
                }

                if ($typeStr === 'income') {
                    return abs((float) $value);
                }

                if ($typeStr === 'expense') {
                    return -abs((float) $value);
                }

                // Pre transfer, buy, sell, dividend, deposit, withdrawal – nechaj tak
                return $value;
            },
        );
    }

    // --- BIZNIS LOGIKA (Model Events) ---
    // Táto časť sa postará o to, aby sa zostatok na účte zmenil automaticky.
    protected static function booted(): void
    {
        static::created(function (Transaction $transaction) {
            $transaction->account->increment('balance', $transaction->amount);
            $transaction->clearDashboardCache();
        });

        static::deleted(function (Transaction $transaction) {
            $transaction->account->decrement('balance', $transaction->amount);
            $transaction->clearDashboardCache();
        });

        static::updating(function (Transaction $transaction) {
            // Ak sa zmenil účet, musíme opraviť zostatky na OBOCH účtoch
            if ($transaction->isDirty('account_id')) {
                $oldAccount = Account::find($transaction->getOriginal('account_id'));
                $oldAmount  = (float) $transaction->getOriginal('amount');
                // Odčítame starú sumu zo starého účtu
                $oldAccount?->decrement('balance', $oldAmount);
                // Pôvodná changed() logika v 'updated' potom pridá novú sumu na nový účet
            }
        });

        static::updated(function (Transaction $transaction) {
            // Výpočet rozdielu medzi starou a novou sumou
            $diff = $transaction->amount - $transaction->getOriginal('amount');
            $transaction->account->increment('balance', $diff);
            
            $transaction->clearDashboardCache();
            // Ak sa zmenil dátum, premažeme aj pôvodný rok (pre istotu)
            if ($transaction->wasChanged('transaction_date')) {
                $oldDate = $transaction->getOriginal('transaction_date');
                if ($oldDate) {
                    $oldYear = \Illuminate\Support\Carbon::parse($oldDate)->year;
                    Cache::forget(DashboardFinanceService::getYearlyCashflowCacheKey($transaction->user_id, $oldYear));
                }
            }
        });

        static::deleting(function (Transaction $transaction) {
            if ($transaction->linked_transaction_id) {
                // Použijeme withoutGlobalScopes, aby sme videli prepojenú transakciu bez obmedzení
                $peer = Transaction::withoutGlobalScopes()->find($transaction->linked_transaction_id);
                if ($peer) {
                    // Odpojíme prepojenie na druhej strane, aby sme predišli nekonečnej slučke
                    $peer->updateQuietly(['linked_transaction_id' => null]);
                    $peer->delete();
                }
            }
        });
    }

    public function clearDashboardCache(): void
    {
        $year = $this->transaction_date?->year ?? now()->year;
        Cache::forget(DashboardFinanceService::getYearlyCashflowCacheKey($this->user_id, $year));
    }
}
