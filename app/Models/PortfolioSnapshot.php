<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

class PortfolioSnapshot extends Model
{
    use BelongsToUser;

    protected $fillable = ['user_id', 'total_invested_eur', 'total_market_value_eur', 'recorded_at'];

    protected $casts = [
        'recorded_at' => 'date',
        'total_invested_eur' => 'decimal:4',
        'total_market_value_eur' => 'decimal:4',
    ];
}