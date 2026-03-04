<?php

namespace App\Filament\Widgets;

use App\Models\Account;
use App\Models\Investment;
use App\Services\CurrencyService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Brick\Math\BigDecimal; // PRIDANÉ
use Brick\Math\RoundingMode; // PRIDANÉ

class NetWorthOverview extends BaseWidget
{
    protected static ?int $sort = 0; 

    protected function getStats(): array
    {
        // 1. VÝPOČET HOTOVOSTI V BANKÁCH
        // Inicializujeme BigDecimal ako nulu
        $bankBalanceBD = BigDecimal::of(0);
        
        $accounts = Account::with('currency')->get();

        foreach ($accounts as $account) {
            // Použijeme našu centrálnu službu pre presný prepočet do EUR
            $converted = CurrencyService::convertToEur(
                (string) $account->balance, 
                $account->currency_id
            );
            $bankBalanceBD = $bankBalanceBD->plus($converted);
        }

        // 2. VÝPOČET INVESTÍCIÍ
        $investmentValueBD = BigDecimal::of(0);
        
        // Načítame aktívne investície so všetkým potrebným pre výpočty v modeli
        $investments = Investment::with(['transactions', 'currency'])
            ->where('is_archived', false)
            ->get();

        foreach ($investments as $investment) {
            // Model Investment nám už vracia string cez BigDecimal, takže len pripočítavame
            $investmentValueBD = $investmentValueBD->plus($investment->current_market_value_eur);
        }

        // 3. CELKOVÝ NET WORTH
        $totalNetWorthBD = $bankBalanceBD->plus($investmentValueBD);

        // 4. VÝPOČET POMEROV (Percentá)
        // Použijeme float až pri finálnom delení pre percentá, pretože percentá nie sú "peniaze"
        $totalFloat = $totalNetWorthBD->toFloat();
        
        $bankPercent = $totalFloat > 0 
            ? ($bankBalanceBD->toFloat() / $totalFloat) * 100 
            : 0;
            
        $investPercent = $totalFloat > 0 
            ? ($investmentValueBD->toFloat() / $totalFloat) * 100 
            : 0;

        return [
            // KARTA: TOTAL NET WORTH
            Stat::make('Čistý majetok (Net Worth)', number_format($totalNetWorthBD->toFloat(), 2, ',', ' ') . ' €')
                ->description('Banka: ' . number_format($bankBalanceBD->toFloat(), 2, ',', ' ') . ' € | Investície: ' . number_format($investmentValueBD->toFloat(), 2, ',', ' ') . ' €')
                ->descriptionIcon('heroicon-m-shield-check')
                ->color('success'),

            // KARTA: CASH POMER
            Stat::make('Likvidná hotovosť', number_format($bankPercent, 1, ',', ' ') . ' %')
                ->description('Peniaze dostupné ihneď')
                ->icon('heroicon-m-banknotes')
                ->color('info'),

            // KARTA: INVESTIČNÝ POMER
            Stat::make('Pomer v investíciách', number_format($investPercent, 1, ',', ' ') . ' %')
                ->description('Aktívny kapitál na trhoch')
                ->icon('heroicon-m-chart-bar')
                ->color('warning'),
        ];
    }
}