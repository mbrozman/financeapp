<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    // Hromadné priradenie: hovoríme Laravelu, ktoré polia smie užívateľ meniť cez formulár
    protected $fillable = ['code', 'name', 'symbol', 'exchange_rate'];

    // Pretypovanie: povieme Laravelu, že exchange_rate je číslo (float/decimal) a nie string
    protected function casts(): array
    {
        return [
            'exchange_rate' => 'decimal:8',
        ];
    }
}
