<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Models\Currency;

class InvestmentsDashboard extends Page
{
    public static function canAccess(): bool
    {
        return !auth()->user() || !auth()->user()->is_superadmin;
    }

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationGroup = '📊 PREHĽADY';
    protected static ?string $navigationLabel = 'Prehľad investícií';
    protected static ?int $navigationSort = 3;
    protected static ?string $title = 'Prehľad investícií';

    protected static string $view = 'filament.pages.investments-dashboard';

    public $currency = 'EUR';

    public function mount()
    {
        $this->currency = 'EUR';
    }

    public function getCurrenciesProperty()
    {
        return Currency::pluck('code', 'code')->toArray();
    }
}
