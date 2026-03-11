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

    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';
    protected static ?string $navigationGroup = 'Investície';
    protected static ?string $navigationLabel = 'Prehľad';
    protected static ?int $navigationSort = 1;
    protected static ?string $title = 'Investičný Dashboard';

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
