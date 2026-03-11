<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class FinanceOverview extends Page
{
    public static function canAccess(): bool
    {
        return !auth()->user() || !auth()->user()->is_superadmin;
    }

    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';
    protected static ?string $navigationGroup = 'Financie';
    protected static ?string $navigationLabel = 'Prehľad';
    protected static ?int $navigationSort = 1;
    protected static ?string $title = 'Prehľad financií';

    protected static string $view = 'filament.pages.finance-overview';
}
