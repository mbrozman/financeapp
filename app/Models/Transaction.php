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
        return $this->belongsTo(Account::class);
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
                // Pozrieme sa, aký typ sme vybrali vo formulári
                // Ak v poli type nič nie je, predpokladáme 'expense' (výdavok)
                $type = $attributes['type'] ?? $this->type ?? 'expense';

                if ($type === 'income') {
                    return abs($value); // Vždy kladné pre príjem
                }

                if ($type === 'expense') {
                    return -abs($value); // Vždy záporné pre výdavok
                }

                return $value; // Pre prevody (transfer) necháme tak
            },
        );
    }

    // --- BIZNIS LOGIKA (Model Events) ---
    // Táto časť sa postará o to, aby sa zostatok na účte zmenil automaticky.
    protected static function booted(): void
    {
        static::created(function (Transaction $transaction) {
            $transaction->account->increment('balance', $transaction->amount);
        });

        static::deleted(function (Transaction $transaction) {
            $transaction->account->decrement('balance', $transaction->amount);
        });

        static::updated(function (Transaction $transaction) {
            // Výpočet rozdielu medzi starou a novou sumou
            $diff = $transaction->amount - $transaction->getOriginal('amount');
            $transaction->account->increment('balance', $diff);
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
}
