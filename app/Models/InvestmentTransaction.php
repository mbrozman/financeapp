<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB; // TENTO RIADOK TU MUSÍ BYŤ

class InvestmentTransaction extends Model
{
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
        'notes',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'quantity' => 'decimal:8',
        'price_per_unit' => 'decimal:4',
        'commission' => 'decimal:4',
        'exchange_rate' => 'decimal:8',
    ];

    public function investment(): BelongsTo
    {
        return $this->belongsTo(Investment::class);
    }
    public function currency(): \Illuminate\Database\Eloquent\Relations\BelongsTo
{
    return $this->belongsTo(Currency::class);
}

    protected static function booted(): void
{
    static::saved(function (InvestmentTransaction $tx) {
        $investment = $tx->investment;

        // 1. Logika peňazí u brokera (táto ti pravdepodobne funguje)
        $brokerAccount = $investment->account;
        $amountEur = ($tx->quantity * ($tx->price_per_unit / $tx->exchange_rate));
        
        if ($tx->type === 'sell') {
            $brokerAccount->increment('balance', $amountEur - $tx->commission);
        } elseif ($tx->type === 'buy') {
            $brokerAccount->decrement('balance', $amountEur + $tx->commission);
        }

        // 2. LOGIKA ARCHIVÁCIE (Opravená)
        $totalBuys = \App\Models\InvestmentTransaction::where('investment_id', $investment->id)->where('type', 'buy')->sum('quantity');
        $totalSells = \App\Models\InvestmentTransaction::where('investment_id', $investment->id)->where('type', 'sell')->sum('quantity');
        
        $currentQty = (float)$totalBuys - (float)$totalSells;

        // Ak je zostatok 0 alebo menej, archivujeme. Ak je viac, vrátime z archívu.
        $investment->updateQuietly([
            'is_archived' => ($currentQty <= 0.000001)
        ]);
    });

    static::deleted(function (InvestmentTransaction $tx) {
        $investment = $tx->investment;
        $totalBuys = \App\Models\InvestmentTransaction::where('investment_id', $investment->id)->where('type', 'buy')->sum('quantity');
        $totalSells = \App\Models\InvestmentTransaction::where('investment_id', $investment->id)->where('type', 'sell')->sum('quantity');
        
        $investment->updateQuietly([
            'is_archived' => (($totalBuys - $totalSells) <= 0.000001)
        ]);
    });
}
}