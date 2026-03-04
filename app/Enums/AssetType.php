<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AssetType: string implements HasLabel
{
    case STOCK = 'stock';
    case ETF = 'etf';
    case CRYPTO = 'crypto';
    case COMMODITY = 'commodity';
    case BOND = 'bond';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::STOCK => 'Akcia',
            self::ETF => 'ETF Fond',
            self::CRYPTO => 'Kryptomena',
            self::COMMODITY => 'Komodita',
            self::BOND => 'Dlhopis',
        };
    }
}