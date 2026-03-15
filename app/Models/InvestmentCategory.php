<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser; // Náš Trait pre bezpečnosť dát
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InvestmentCategory extends Model
{
    use BelongsToUser, SoftDeletes, HasFactory; 

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'icon',
        'color',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Vzťah: Jedna kategória (napr. ETF) môže mať priradených veľa investícií (napr. VWCE, SXR8)
    public function investments(): HasMany
    {
        return $this->hasMany(Investment::class);
    }
}