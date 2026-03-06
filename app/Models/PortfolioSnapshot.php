<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

class PortfolioSnapshot extends Model
{
    use BelongsToUser;

    // TENTO RIADOK OPRAVÍ TVOJU CHYBU:
    protected $table = 'net_worth_snapshots';

    protected $fillable = [
        'user_id', 
        'total_invested_eur', 
        'total_liquid_cash_eur', // Pridali sme predtým v migrácii
        'total_market_value_eur', 
        'recorded_at'
    ];

    protected $casts = [
        'recorded_at' => 'date',
        'total_invested_eur' => 'string', // Použijeme string kvôli BigDecimal
        'total_market_value_eur' => 'string',
        'total_liquid_cash_eur' => 'string',
    ];
}