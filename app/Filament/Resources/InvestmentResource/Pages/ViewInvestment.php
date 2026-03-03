<?php

namespace App\Filament\Resources\InvestmentResource\Pages;

use App\Filament\Resources\InvestmentResource;
use App\Services\StockApiService;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Notifications\Notification;

// 1. IMPORTY NAŠICH WIDGETOV
use App\Filament\Resources\InvestmentResource\Widgets\IndividualInvestmentChart;
use App\Filament\Resources\InvestmentResource\Widgets\InvestmentProfitStats;

class ViewInvestment extends ViewRecord
{
    protected static string $resource = InvestmentResource::class;

    /**
     * TLAČIDLÁ V HLAVIČKE (Refresh a Edit)
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh_data')
                ->label('Aktualizovať z trhu')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->action(function (StockApiService $api) {
                    $record = $this->getRecord();
                     \Illuminate\Support\Facades\Cache::forget("stock_price_{$record->ticker}");
                     $liveData = $api->getLiveQuote($record->ticker);
                    // Stiahneme aktuálnu cenu
                    $liveData = $api->getLiveQuote($record->ticker);
                    if ($liveData) {
                        $record->update([
                            'current_price' => $liveData['price'],
                            'daily_change_percentage' => $liveData['change_percent'],
                            'last_price_update' => now(),
                        ]);
                    }

                    // Stiahneme históriu pre graf
                    $api->downloadHistory($record, 365);

                    Notification::make()
                        ->title('Dáta boli úspešne aktualizované')
                        ->success()
                        ->send();

                    return redirect()->to(InvestmentResource::getUrl('view', ['record' => $record]));
                }),

            \Filament\Actions\EditAction::make(),
            Action::make('archive')
                ->label('Archivovať pozíciu')
                ->icon('heroicon-o-archive-box')
                ->color('warning')
                ->requiresConfirmation() // Opýta sa: "Ste si istý?"
                ->action(function () {
                    $this->record->update(['is_archived' => true]);
                    Notification::make()->title('Pozícia bola archivovaná')->success()->send();
                })
                // Zobrazí sa len ak ešte NIE JE archivovaná
                ->visible(fn () => !$this->record->is_archived && $this->record->total_quantity < 0.01),
        ];
    }

    /**
     * 2. REGISTRÁCIA WIDGETOV
     * Tu hovoríme stránke, ktoré komponenty má zobraziť v hlavičke.
     */
    protected function getHeaderWidgets(): array
    {
        return [
            InvestmentProfitStats::class,   // Vľavo: Karty so ziskom
            IndividualInvestmentChart::class, // Vpravo: Graf
        ];
    }

public function mount(int | string $record): void
{
    // 1. Zavoláme pôvodnú funkciu, aby sa načítal záznam (napr. AMD)
    parent::mount($record);

    // 2. Skontrolujeme, či v tabuľke histórie niečo je
    // Použijeme náš nový vzťah priceHistories()
    if ($this->getRecord()->priceHistories()->count() === 0) {
        
        // 3. Ak je tam 0 riadkov, na pozadí skúsime stiahnuť posledných 30 dní
        // Použijeme app() helper na zavolanie našej služby
        app(\App\Services\StockApiService::class)->downloadHistory($this->getRecord(), 30);
        
        // 4. "Osviežime" dáta v pamäti, aby widgety videli, že tabuľka sa naplnila
        $this->getRecord()->refresh();
    }
}

    public function getTitle(): string
    {
        // Vráti napr. "AMD | Advanced Micro Devices"
        return "{$this->record->ticker} | {$this->record->name}";
    }

    /**
     * 3. ROZDELENIE NA STĹPCE
     * Toto je kľúčové pre UX. Povieme Filamentu, aby hlavičku rozdelil na 2 stĺpce.
     * Na veľkej obrazovke budú vedľa seba, na mobile pod sebou.
     */
    public function getHeaderWidgetsColumns(): int | string | array
    {
        return 2;
    }

    /**
     * 4. ODOSLANIE DÁT DO WIDGETOV
     * Týmto povieme widgetom: "Tu máš konkrétnu akciu (napr. AMD), s ktorou máš pracovať."
     */
    protected function getHeaderWidgetsData(): array
    {
        return [
            'record' => $this->getRecord(),
        ];
    }
}
