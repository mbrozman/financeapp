<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvestmentPriceHistory extends Model
{
    protected $fillable = ['investment_id', 'price', 'recorded_at'];

    protected $casts = [
        'recorded_at' => 'date',
        'price' => 'decimal:4'
    ];

    public function investment(): BelongsTo
    {
        return $this->belongsTo(Investment::class);
    }
}
