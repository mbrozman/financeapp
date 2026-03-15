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
        'user_id', 'parent_id', 'name', 'type', 'icon', 'color', 'financial_plan_item_id', 'monthly_limit'
    ];

    // VZŤAHY
    public function parent(): BelongsTo { return $this->belongsTo(Category::class, 'parent_id'); }
    public function children(): HasMany { return $this->hasMany(Category::class, 'parent_id'); }
    public function planItem(): BelongsTo { return $this->belongsTo(FinancialPlanItem::class, 'financial_plan_item_id'); }

    public static function getPremiumPalette(): array
    {
        return [
            '#ef4444', // Red
            '#f97316', // Orange
            '#eab308', // Yellow
            '#22c55e', // Green
            '#06b6d4', // Cyan
            '#3b82f6', // Blue
            '#a855f7', // Purple
            '#ec4899', // Pink
            '#78350f', // Brown
            '#64748b', // Slate/Grey
        ];
    }

    /**
     * Dedenie farby od rodiča a automatické tieňovanie pre podkategórie
     */
    protected function effectiveColor(): Attribute
    {
        return Attribute::make(
            get: function () {
                $baseColor = $this->color ?? $this->parent?->color ?? '#94a3b8';
                
                // Ak je to podkategória, vygenerujeme radikálne iný odtieň
                if ($this->parent_id) {
                    // Použijeme ID na deterministický výber "štýlu" tieňovania
                    $index = ($this->id % 5); // 0 až 4
                    
                    return self::generateHSLShade($baseColor, $index);
                }
                
                return $baseColor;
            }
        );
    }

    /**
     * Vygeneruje radikálne iný odtieň pomocou HSL
     */
    public static function generateHSLShade(string $hex, int $index): string
    {
        $hex = str_replace('#', '', $hex);
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        $r = hexdec(substr($hex, 0, 2)) / 255;
        $g = hexdec(substr($hex, 2, 2)) / 255;
        $b = hexdec(substr($hex, 4, 2)) / 255;

        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;
        
        if ($max == $min) {
            $h = $s = 0;
        } else {
            $d = $max - $min;
            $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
            switch ($max) {
                case $r: $h = ($g - $b) / $d + ($g < $b ? 6 : 0); break;
                case $g: $h = ($b - $r) / $d + 2; break;
                case $b: $h = ($r - $g) / $d + 4; break;
            }
            $h /= 6;
        }

        // Radikálne úpravy podľa indexu (0-4)
        // Cieľom je vytvoriť jasne odlíšené verzie, ktoré ale stále vyzerajú ako pôvodná farba
        // Bezpečnejší rozsah pre L je cca 0.40 až 0.85
        switch ($index) {
            case 0: // Veľmi svetlá (Pastel)
                $l = 0.85; $s = min(1, $s * 1.1); break;
            case 1: // Stredne svetlá
                $l = 0.70; $s = min(1, $s * 1.0); break;
            case 2: // Sýta stredná
                $l = 0.55; $s = min(1, $s * 1.2); break;
            case 3: // Jemne tmavšia (ale nie hnedá)
                $l = 0.45; $s = min(1, $s * 1.0); break;
            case 4: // Vyblednutá / Jemná
                $l = 0.75; $s = $s * 0.6; break;
        }

        // Späť na RGB
        $hueToRgb = function($p, $q, $t) {
            if ($t < 0) $t += 1;
            if ($t > 1) $t -= 1;
            if ($t < 1/6) return $p + ($q - $p) * 6 * $t;
            if ($t < 1/2) return $q;
            if ($t < 2/3) return $p + ($q - $p) * (2/3 - $t) * 6;
            return $p;
        };

        if ($s == 0) {
            $r = $g = $b = $l;
        } else {
            $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
            $p = 2 * $l - $q;
            $r = $hueToRgb($p, $q, $h + 1/3);
            $g = $hueToRgb($p, $q, $h);
            $b = $hueToRgb($p, $q, $h - 1/3);
        }

        return sprintf('#%02x%02x%02x', round($r * 255), round($g * 255), round($b * 255));
    }
}