<?php

namespace App\Filament\Pages;

use App\Services\MonthlyBudgetService;
use Filament\Pages\Page;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class MonthlyBudget extends Page
{
    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel = 'Mesačný rozpočet';
    protected static ?string $navigationGroup = '📊 PREHĽADY';
    protected static ?int $navigationSort = 2;
    protected static string $view = 'filament.pages.monthly-budget';

    public $selectedMonth;

    public function mount()
    {
        // Nastavíme aktuálny mesiac pri štarte
        $this->selectedMonth = now()->format('Y-m');
    }

    /**
     * HLAVNÝ VÝPOČET DÁT PRE STRÁNKU
     */
    public function getBudgetData(): array
    {
        return app(MonthlyBudgetService::class)->getBudgetData($this->selectedMonth, Auth::id());
    }

    // Navigačné metódy
    public function previousMonth() 
    { 
        $this->selectedMonth = Carbon::parse($this->selectedMonth . '-01')->subMonth()->format('Y-m'); 
    }

    public function nextMonth() 
    { 
        $this->selectedMonth = Carbon::parse($this->selectedMonth . '-01')->addMonth()->format('Y-m'); 
    }
}
