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
            // 1. NOVÁ POZÍCIA
            Actions\CreateAction::make()
                ->label('Pridať novú pozíciu (Ticker)'),

            // 2. PREPOČET VŠETKÉHO (Fix pre synchronizáciu)
            Actions\Action::make('sync_all')
                ->label('Prepočítať všetko')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->action(function () {
                    \App\Models\Investment::withoutGlobalScopes()->get()->each(function ($inv) {
                        \App\Services\InvestmentCalculationService::refreshStats($inv);
                    });

                    \Filament\Notifications\Notification::make()
                        ->title('Všetky štatistiky boli úspešne prepočítané')
                        ->success()
                        ->send();
                }),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [];
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

            // 2. ZÁLOŽKA: Len ukončené obchody (musíme vypnúť globálny filter)
            'archived' => Tab::make('Archív (Predané)')
                ->icon('heroicon-m-archive-box')
                ->modifyQueryUsing(fn (Builder $query) => $query->withoutGlobalScopes()->where('is_archived', true)),
        ];
    }
}