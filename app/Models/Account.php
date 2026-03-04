<?php

namespace App\Models;

use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use App\Models\Traits\BelongsToUser; // Importujeme náš Trait
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes; 

class Account extends Model
{
    // Aplikujeme Multi-tenancy. Odteraz tento model 
    // VŽDY odfiltruje dáta podľa prihláseného užívateľa.
    use BelongsToUser, LogsActivity,SoftDeletes;

    protected $fillable = ['currency_id', 'name', 'type', 'balance', 'is_active'];
      protected $casts = [
        'balance'   => 'string',  
        'is_active' => 'boolean',
    ];
 public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable() // Loguj všetky polia, ktoré sú vo $fillable
            ->logOnlyDirty() // Loguj len tie polia, ktoré sa reálne zmenili
            ->dontSubmitEmptyLogs(); // Nevytváraj záznam, ak sa nič nezmenilo
    }
    // Definujeme vzťah k Mene (Account patrí k Mene)
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}