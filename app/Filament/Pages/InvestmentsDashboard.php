<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Models\Currency;

class InvestmentsDashboard extends Page
{
    public static function canAccess(): bool
    {
        return true;
    }

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationGroup = '📊 PREHĽADY';
    protected static ?string $navigationLabel = 'Prehľad investícií';
    protected static ?int $navigationSort = 3;
    protected static ?string $title = 'Prehľad investícií';

    protected static string $view = 'filament.pages.investments-dashboard';

    public function mount()
    {
        // No longer needed: currency is handled globally via session
    }
}
