<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Currency extends Model
{
    use HasFactory;
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
