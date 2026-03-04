<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TransactionType: string implements HasLabel, HasColor
{
    case BUY = 'buy';
    case SELL = 'sell';
    case DIVIDEND = 'dividend';
    case FEE = 'fee';
    case DEPOSIT = 'deposit';
    case WITHDRAWAL = 'withdrawal';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::BUY => 'Nákup',
            self::SELL => 'Predaj',
            self::DIVIDEND => 'Dividenda',
            self::FEE => 'Poplatok',
            self::DEPOSIT => 'Vklad hotovosti',
            self::WITHDRAWAL => 'Výber hotovosti',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::BUY, self::DEPOSIT => 'success',
            self::SELL, self::WITHDRAWAL => 'danger',
            self::DIVIDEND => 'info',
            self::FEE => 'warning',
        };
    }
}