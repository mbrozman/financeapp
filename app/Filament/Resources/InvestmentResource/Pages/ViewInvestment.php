<?php

namespace App\Filament\Resources\InvestmentResource\Pages;

use App\Filament\Resources\InvestmentResource;
use App\Filament\Resources\InvestmentResource\Widgets\IndividualInvestmentChart;
use App\Filament\Resources\InvestmentResource\Widgets\InvestmentProfitStats;
use App\Services\StockApiService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Cache;

class ViewInvestment extends ViewRecord
{
    protected static string $resource = InvestmentResource::class;

    /**
     * HLAVIČKOVÉ AKCIE
     */
    protected function getHeaderActions(): array
    {
        return [
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

                    // Obnovíme stránku
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
     */
    protected function getHeaderWidgetsData(): array
    {
        $record = $this->getRecord();
        
        $record->load(['transactions', 'currency']);
        
        return [
            'record' => $record,
        ];
    }
}