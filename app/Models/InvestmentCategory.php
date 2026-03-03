<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser; // Náš Trait pre bezpečnosť dát
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvestmentCategory extends Model
{
    use BelongsToUser; // Zabezpečí, že užívateľ uvidí len svoje kategórie

    // Polia, ktoré môžeme hromadne zapisovať
    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'icon',
        'color',
    ];

    // Vzťah: Jedna kategória (napr. ETF) môže mať priradených veľa investícií (napr. VWCE, SXR8)
    public function investments(): HasMany
    {
        return $this->hasMany(Investment::class);
    }
}