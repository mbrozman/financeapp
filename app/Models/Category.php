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

    /**
     * Vygeneruje odtieň farby na základe vstupného HEXu
     */
    public static function generateShadedColor(string $hex, int $percent = 20): string
    {
        $hex = str_replace('#', '', $hex);
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        // Ak je farba tmavá, zosvetlíme. Ak svetlá, stmavíme.
        $brightness = ($r * 299 + $g * 587 + $b * 114) / 1000;
        $step = $brightness > 128 ? -$percent : $percent;

        $r = max(0, min(255, $r + $step));
        $g = max(0, min(255, $g + $step));
        $b = max(0, min(255, $b + $step));

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }
}