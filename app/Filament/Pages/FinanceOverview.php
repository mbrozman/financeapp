<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class FinanceOverview extends Page
{
    public static function canAccess(): bool
    {
        return true;
    }

    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';
    protected static ?string $navigationGroup = '📊 PREHĽADY';
    protected static ?string $navigationLabel = 'Prehľad financií';
    protected static ?int $navigationSort = 1;
    protected static ?string $title = 'Prehľad financií';

    protected static string $view = 'filament.pages.finance-overview';
}
