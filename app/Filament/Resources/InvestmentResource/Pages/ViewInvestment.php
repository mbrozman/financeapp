<?php

namespace App\Filament\Resources\InvestmentResource\Pages;

use App\Filament\Resources\InvestmentResource;
use App\Services\StockApiService;
use App\Filament\Resources\InvestmentResource\Widgets\IndividualInvestmentChart;
use App\Filament\Resources\InvestmentResource\Widgets\InvestmentProfitStats;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Cache;

class ViewInvestment extends ViewRecord
{
    protected static string $resource = InvestmentResource::class;

    /**
     * TLAČIDLÁ V HLAVIČKE
     */
    protected function getHeaderActions(): array
    {
        $isEur = request()->query('currency') === 'EUR';
        $record = $this->getRecord();
        $isNativeEur = $record->currency?->code === 'EUR';

        return [
            // 0. PREPÍNAČ MENY (Zobraziť len ak pôvodná mena nie je EUR)
            Action::make('toggle_currency')
                ->label($isEur ? 'Zobraziť v ' . ($record->currency?->code ?? 'USD') : 'Zobraziť v EUR')
                ->icon('heroicon-o-currency-euro')
                ->color('success')
                ->url(fn () => $isEur 
                    ? static::getResource()::getUrl('view', ['record' => $record])
                    : static::getResource()::getUrl('view', ['record' => $record, 'currency' => 'EUR'])
                )
                ->visible(!$isNativeEur),
            // 1. AKTUALIZÁCIA CIEN
            Action::make('refresh_data')
                ->label('Aktualizovať z trhu')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->action(function (StockApiService $api) {
                    $record = $this->getRecord();
                    
                    // Vymažeme starú cenu z pamäte (Cache)
                    Cache::forget("stock_price_{$record->ticker}");
                    
                    // Stiahneme novú cenu
                    $liveData = $api->getLiveQuote($record->ticker);
                    
                    if ($liveData) {
                        $record->update([
                            'current_price' => $liveData['price'],
                            'daily_change_percentage' => $liveData['change_percent'],
                            'last_price_update' => now(),
                        ]);
                    }

                    // Stiahneme históriu pre graf
                    $api->downloadHistory($record, 30);

                    Notification::make()
                        ->title('Dáta úspešne aktualizované')
                        ->success()
                        ->send();

                    // Obnovíme stránku, aby sa prepočítali widgety
                    return redirect()->to(InvestmentResource::getUrl('view', ['record' => $record]));
                }),

            // 2. ARCHIVÁCIA
            Action::make('archive')
                ->label('Archivovať')
                ->icon('heroicon-o-archive-box')
                ->color('warning')
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->update(['is_archived' => true]);
                    Notification::make()->title('Pozícia archivovaná')->success()->send();
                })
                ->visible(fn () => !$this->record->is_archived),

            EditAction::make(),
        ];
    }

    /**
     * REGISTRÁCIA WIDGETOV (Stats vľavo, Graf vpravo)
     */
    protected function getHeaderWidgets(): array
    {
        return [
            InvestmentProfitStats::class,
            IndividualInvestmentChart::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int | string | array
    {
        return 2;
    }

    /**
     * ODOSLANIE DÁT DO WIDGETOV
     * Pridal som refresh(), aby widgety nikdy nevideli 0
     */
    protected function getHeaderWidgetsData(): array
    {
        $record = $this->getRecord();
        
        // Eager load transakcií pre widgety, aby nepočítali z nuly
        $record->load(['transactions', 'currency']);
        
        return [
            'record' => $this->getRecord()->load(['transactions', 'currency']),
        ];
    }
}