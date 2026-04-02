<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Goal extends Model
{
    use BelongsToUser;

    protected $fillable = [
        'user_id',
        'name',
        'target_amount',
        'current_amount',
        'deadline',
        'type',
        'color',
        'is_reserve'
    ];

    protected $casts = [
        'target_amount' => 'decimal:4',
        'current_amount' => 'decimal:4',
        'deadline' => 'date',
        'is_reserve' => 'boolean',
    ];

    public function accounts(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Account::class);
    }

    public function investments(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Investment::class);
    }

    // --- VLASTNÝ GETTER PRE AKTUÁLNY STAV ---
    protected function currentAmount(): Attribute
    {
        return Attribute::make(
            get: function ($value, $attributes) {
                // 1. Sčítame zostatky všetkých prepojených účtov (Hotovosť)
                $accountsTotal = 0;
                if ($this->accounts()->exists()) {
                    $accountsTotal = $this->accounts()->get()->sum(function($acc) {
                        return (float) \App\Services\CurrencyService::convertToEur($acc->balance ?? 0, $acc->currency_id);
                    });
                }

                // 2. Sčítame hodnotu konkrétne prepojených investícií (Selektívne ETF)
                $investmentsTotal = 0;
                if ($this->investments()->exists()) {
                    $investmentsTotal = $this->investments()->get()->sum(function($inv) {
                        return (float) $inv->current_market_value_eur;
                    });
                }

                return $accountsTotal + $investmentsTotal;
            }
        );
    }

    // --- VÝPOČET PROGRESU ---
    // Toto nám vráti číslo od 0 do 100
    protected function progress(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->target_amount <= 0) return 0;
                
                $percentage = ($this->current_amount / $this->target_amount) * 100;
                
                return round($percentage, 2);
            },
        );
    }

    protected function color(): Attribute
{
    return Attribute::make(
        set: function ($value) {
            // Regulárny výraz: musí začínať # a nasledovať 3 alebo 6 HEX znakov
            if (!preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $value)) {
                return '#3b82f6'; // Ak je to podozrivé, vrátime predvolenú modrú
            }
            return $value;
        },
    );
}
}