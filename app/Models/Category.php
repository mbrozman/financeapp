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
            '#ff0000', // Red
            '#87ceeb', // Blue
            '#228b22', // Green
            '#ffbf00', // Yellow
            '#232323', // Black
            '#a855f7', // Purple
            '#78350f', // Brown
            '#64748b', // Slate
        ];
    }

    /**
     * Mapovanie Pilier -> 5 Štýlov hlavných kategórií
     */
    public static function getStylePalette(): array
    {
        return [
            '#ff0000' => ['#f08080', '#ff035b', '#e34234', '#800020', '#ff0000'],
            '#87ceeb' => ['#87ceeb', '#4169e1', '#03eeff', '#6666b3', '#3113f5'],
            '#228b22' => ['#98ff98', '#bada55', '#228b22', '#3fff0f', '#319984'],
            '#ffbf00' => ['#e1ad01', '#ffbf00', '#ffef00', '#f59e0b', '#d97706'],
            '#232323' => ['#232323', '#3a3a3a', '#515151', '#686868', '#808080'],
        ];
    }

    /**
     * Mapovanie Štýl -> Sekvenčné odtiene podkategórií
     */
    public static function getSubcategoryPalette(): array
    {
        return [
            // Červené
            '#f08080' => ['#c06666', '#904d4d', '#603333', '#f39999', '#f6b3b3', '#f9cccc'],
            '#ff035b' => ['#cc0249', '#990237', '#660124', '#ff357c', '#ff689d', '#ff9abd'],
            '#e34234' => ['#b6352a', '#88281f', '#5b1a15', '#e9685d', '#ee8e85', '#f4b3ae'],
            '#800020' => ['#66001a', '#4d0013', '#33000d', '#99334d', '#b36679', '#cc99a6'],
            '#ff0000' => ['#cc0000', '#990000', '#660000', '#ff3333', '#ff6666', '#ff9999'],
            
            // Modré
            '#87ceeb' => ['#6ca5bc', '#517c8d', '#36525e', '#9fd8ef', '#cfebf7', '#e7f5fb'],
            '#4169e1' => ['#b3c3f3', '#8da5ed', '#6787e7', '#1a2a5a', '#273f87', '#3454b4'],
            '#03eeff' => ['#9af8ff', '#68f5ff', '#35f1ff', '#015f66', '#028f99', '#02becc'],
            '#6666b3' => ['#c2c2e1', '#a3a3d1', '#8585c2', '#292948', '#3d3d6b', '#52528f'],
            '#3113f5' => ['#ada1fb', '#8371f9', '#5a42f7', '#140862', '#1d0b93', '#270fc4'],

            // Zelené
            '#98ff98' => ['#d6ffd6', '#c1ffc1', '#adffad', '#3d663d', '#5b995b', '#7acc7a'],
            '#bada55' => ['#e3f0bb', '#d6e999', '#c8e177', '#4a5722', '#708333', '#95ae44'],
            '#228b22' => ['#a7d1a7', '#7ab97a', '#4ea24e', '#0e380e', '#145314', '#1b6f1b'],
            '#3fff0f' => ['#b2ff9f', '#8cff6f', '#65ff3f', '#196606', '#269909', '#32cc0c'],
            '#319984' => ['#add6ce', '#83c2b5', '#5aad9d', '#143d35', '#1d5c4f', '#277a6a'],

            // Žlté
            '#e1ad01' => ['#f3de99', '#edce67', '#e7bd34', '#5a4500', '#876801', '#b48a01'],
            '#ffbf00' => ['#fff2cc', '#ffe599', '#ffd966', '#ffcc33', '#ffbf00'],
            '#ffef00' => ['#fff999', '#fff566', '#fff233', '#666000', '#998f00', '#ccbf00'],

            // Čierna
            '#232323' => ['#3a3a3a', '#515151', '#686868', '#808080', '#979797', '#aeaeae', '#c5c5c5', '#dcdcdc'],
        ];
    }

    /**
     * Dedenie farby od rodiča a automatické tieňovanie pre podkategórie
     */
    protected function effectiveColor(): Attribute
    {
        return Attribute::make(
            get: function () {
                // 1. Ak je to podkategória, aplikujeme inteligentný algoritmus sekvenčného posunu
                if ($this->parent_id) {
                    $parentColor = $this->parent?->color ?? $this->parent?->effective_color ?? '#94a3b8';
                    
                    // Zistíme poradie (index) položky medzi súrodencami pre plynulý prechod
                    $index = self::where('parent_id', $this->parent_id)
                        ->where('id', '<=', $this->id)
                        ->count() - 1;

                    return self::applySubcategoryShift($parentColor, $index);
                }

                // 2. Ak má hlavná kategória vlastnú farbu, použijeme ju
                if ($this->color) return $this->color;

                // 3. Fallback na pilier
                return $this->planItem?->color ?? '#94a3b8';
            }
        );
    }

    /**
     * Aplikuje posun odtieňa a svetlosti pre podkategórie - prioritne používa hardcoded paletu
     */
    public static function applySubcategoryShift(string $hex, int $index): string
    {
        $hex = str_starts_with($hex, '#') ? $hex : "#{$hex}";
        $palette = self::getSubcategoryPalette();

        if (isset($palette[$hex])) {
            $shades = $palette[$hex];
            return $shades[$index % count($shades)];
        }

        // HSL Fallback pre farby mimo registra
        $hsl = self::hexToHsl($hex);
        $hsl['h'] = ($hsl['h'] + ($index + 1) * 15) % 360;
        $hsl['l'] = max(5, min(95, $hsl['l'] - (($index + 1) * 5)));
        
        return self::hslToHex($hsl['h'], $hsl['s'], $hsl['l']);
    }

    /**
     * Vygeneruje pole 5 štýlov pre danú základnú farbu (HEX)
     */
    public static function getShadesForBase(string $hex): array
    {
        $shades = [];
        for ($i = 0; $i < 5; $i++) {
            $shades[] = self::generateHSLShade($hex, $i);
        }
        return $shades;
    }

    /**
     * Vygeneruje odtieň - prioritne používa hardcoded paletu štýlov
     */
    public static function generateHSLShade(string $hex, int $index): string
    {
        $hex = str_starts_with($hex, '#') ? $hex : "#{$hex}";
        $palette = self::getStylePalette();

        if (isset($palette[$hex])) {
            $styles = $palette[$hex];
            return $styles[$index % count($styles)];
        }

        // HSL Fallback pre farby mimo registra
        $hsl = self::hexToHsl($hex);
        switch ($index) {
            case 0: $hsl['s'] = 100; $hsl['l'] = 50; break; // Vibrant
            case 1: $hsl['s'] = 30;  $hsl['l'] = 50; break; // Muted
            case 2: $hsl['s'] = 70;  $hsl['l'] = 85; break; // Pastel
            case 3: $hsl['s'] = 80;  $hsl['l'] = 25; break; // Deep
            case 4: $hsl['s'] = 90;  $hsl['l'] = 60; break; // Neon
        }

        return self::hslToHex($hsl['h'], $hsl['s'], $hsl['l']);
    }

    public static function hexToHsl(string $hex): array
    {
        $hex = str_replace('#', '', $hex);
        if (strlen($hex) === 3) $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];

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

        return ['h' => $h * 360, 's' => $s * 100, 'l' => $l * 100];
    }

    public static function hslToHex(float $h, float $s, float $l): string
    {
        $h /= 360; $s /= 100; $l /= 100;

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

    /**
     * Výpočet reálneho čerpania (sumy transakcií) pre daný mesiac.
     */
    public function actualAmount(string $month): float
    {
        $date = \Carbon\Carbon::parse($month . '-01');
        
        $categoryIds = self::where('id', $this->id)
            ->orWhere('parent_id', $this->id)
            ->pluck('id');

        return (float) Transaction::whereIn('category_id', $categoryIds)
            ->whereMonth('transaction_date', $date->month)
            ->whereYear('transaction_date', $date->year)
            ->sum('amount');
    }
}