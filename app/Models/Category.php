<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Category extends Model
{
    use BelongsToUser;

    protected $fillable = [
        'user_id', 'parent_id', 'name', 'type', 'icon', 'color', 'financial_plan_item_id'
    ];

    // VZŤAHY
    public function parent(): BelongsTo { return $this->belongsTo(Category::class, 'parent_id'); }
    public function children(): HasMany { return $this->hasMany(Category::class, 'parent_id'); }
    public function planItem(): BelongsTo { return $this->belongsTo(FinancialPlanItem::class, 'financial_plan_item_id'); }

    /**
     * Dedenie farby od rodiča pre podkategórie
     */
    protected function effectiveColor(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->color ?? $this->parent?->color ?? '#808080'
        );
    }
}