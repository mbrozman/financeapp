<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Goal extends Model
{
    use BelongsToUser;

    protected $fillable = [
        'name',
        'target_amount',
        'current_amount',
        'deadline',
        'type',
        'color'
    ];

    protected $casts = [
        'target_amount' => 'decimal:4',
        'current_amount' => 'decimal:4',
        'deadline' => 'date',
    ];

    // --- VÝPOČET PROGRESU ---
    // Toto nám vráti číslo od 0 do 100
    protected function progress(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->target_amount <= 0) return 0;
                
                $percentage = ($this->current_amount / $this->target_amount) * 100;
                
                // Ošetríme, aby to nepresiahlo 100%, ak nasporíme viac
                return min(round($percentage, 2), 100);
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