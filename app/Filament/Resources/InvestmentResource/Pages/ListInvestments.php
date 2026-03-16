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
            Actions\Action::make('set_currency')
                ->label(function() {
                    $code = request()->query('table_currency');
                    return $code ? "Mena: {$code}" : 'Pôvodná mena';
                })
                ->icon('heroicon-o-currency-dollar')
                ->color('gray')
                ->form([
                    Forms\Components\Select::make('table_currency')
                        ->label('Zobraziť tabuľku v mene:')
                        ->options(\App\Models\Currency::pluck('code', 'code')->prepend('Pôvodná mena (vlastná pre každé aktívo)', '')->toArray())
                        ->default(request()->query('table_currency'))
                ])
                ->action(function (array $data) {
                    $params = [];
                    if ($data['table_currency']) {
                        $params['table_currency'] = $data['table_currency'];
                    }
                    return redirect()->to(InvestmentResource::getUrl('index', $params));
                }),

            Actions\CreateAction::make()
                ->label('Pridať novú pozíciu (Ticker)'),
        ];
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