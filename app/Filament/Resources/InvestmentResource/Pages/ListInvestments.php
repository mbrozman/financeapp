<?php

namespace App\Filament\Resources\InvestmentResource\Pages;

use App\Filament\Resources\InvestmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab; // Import pre Tab
use Illuminate\Database\Eloquent\Builder; // Import pre Builder

use Filament\Forms;

class ListInvestments extends ListRecords
{
    protected static string $resource = InvestmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Pridať novú pozíciu (Ticker)'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Widgets\CurrencySwitcher::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int | string | array
    {
        return 1;
    }

    // --- TOTO JE OPRAVENÁ SEKCIA ZÁLOŽIEK ---
    public function getTabs(): array
    {
        return [
            // 1. ZÁLOŽKA: Len to, čo reálne vlastním
            'active' => Tab::make('Moje Portfólio')
                ->icon('heroicon-m-briefcase')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_archived', false)),

            // 2. ZÁLOŽKA: Len ukončené obchody
            'archived' => Tab::make('Archív (Predané)')
                ->icon('heroicon-m-archive-box')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_archived', true)),

            // 3. ZÁLOŽKA: Všetko spolu
            'all' => Tab::make('Všetko spolu'),
        ];
    }
}