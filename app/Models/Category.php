<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser; // Import nášho Traitu pre bezpečnosť
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use BelongsToUser; // Zabezpečí, že uvidím len svoje kategórie

    protected $fillable = [
        'user_id',
        'parent_id',
        'name',
        'type',
        'icon',
        'color',
        'financial_plan_item_id', // PRIDANÉ
    ];

    // Vzťah: Kategória môže mať nadradenú kategóriu (Parent)
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    // Vzťah: Kategória môže mať veľa podkategórií (Children)
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }
    public function planItem()
    {
        return $this->belongsTo(FinancialPlanItem::class, 'financial_plan_item_id');
    }
}
