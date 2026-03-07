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

    case INCOME = 'income';
    case EXPENSE = 'expense';
    case TRANSFER = 'transfer';


    public function getLabel(): ?string
    {
        return match ($this) {
            self::BUY => 'Nákup',
            self::SELL => 'Predaj',
            self::DIVIDEND => 'Dividenda',
            self::FEE => 'Poplatok',
            self::INCOME => 'Príjem (Výplata/Iné)',
            self::EXPENSE => 'Výdavok (Spotreba)',
            self::TRANSFER => 'Interný prevod',
            self::DEPOSIT => 'Vklad hotovosti',
            self::WITHDRAWAL => 'Výber hotovosti',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::BUY, self::INCOME, self::DEPOSIT => 'success',
            self::SELL, self::EXPENSE, self::WITHDRAWAL => 'danger',
            self::DIVIDEND => 'info',
            self::FEE, self::TRANSFER => 'warning',
        };
    }
}
