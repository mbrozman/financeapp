<?php

namespace App\Filament\Resources\InvestmentPlanResource\Pages\Traits;

use App\Models\Investment;
use App\Models\InvestmentPlan;
use App\Models\InvestmentTransaction;
use App\Models\Currency;
use App\Enums\TransactionType;
use App\Services\StockApiService;
use App\Services\CurrencyService;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

trait HasInvestmentPlanSync
{
    protected function syncItems(InvestmentPlan $record, array $itemsData, array $allData): void
    {
        $userId = auth()->id();
        $apiService = app(StockApiService::class);
        
        // Vyčistíme staré položky
        $record->items()->delete();

        foreach ($itemsData as $item) {
            $ticker = strtoupper($item['ticker'] ?? '');
            if (!$ticker) continue;

            $quote = $apiService->getLiveQuote($ticker);
            $profile = $apiService->getExtendedProfile($ticker);
            $assetType = $profile['asset_type'] ?? 'ETF';
            $categoryName = $assetType === 'ETF' ? 'ETF / Fondy' : 'Akcie';

            $categoryId = \App\Models\InvestmentCategory::where('user_id', $userId)
                ->where('name', 'LIKE', $categoryName . '%')
                ->first()?->id ?? \App\Models\InvestmentCategory::where('user_id', $userId)->first()?->id;

            $investment = Investment::firstOrCreate(
                ['user_id' => $userId, 'ticker' => $ticker, 'account_id' => $allData['account_id']],
                [
                    'name' => $quote['name'] ?? $ticker,
                    'currency_id' => Currency::where('code', $quote['currency'] ?? 'EUR')->first()?->id ?? $allData['currency_id'],
                    'investment_category_id' => $categoryId,
                    'broker' => \App\Models\Account::find($allData['account_id'])?->name ?? 'XTB',
                    'asset_type' => $assetType,
                    'current_price' => $quote['price'] ?? '0',
                    'last_price_update' => now(),
                ]
            );

            $record->items()->create([
                'investment_id' => $investment->id,
                'weight' => $item['weight'] ?? 100,
            ]);
        }
    }

    protected function createInitialTransaction(InvestmentPlan $plan, array $itemsData, array $data): void
    {
        $totalInitialValue = BigDecimal::of($data['initial_total_value'] ?? 0);
        $totalInvested = BigDecimal::of($data['initial_invested_amount'] ?? 0);

        if ($totalInitialValue->isZero()) return;

        $apiService = app(StockApiService::class);

        foreach ($itemsData as $item) {
            $ticker = $item['ticker'] ?? null;
            if (!$ticker) continue;

            $weightStr = (string) ($item['weight'] ?? 100);
            $weightMultiplier = BigDecimal::of($weightStr)->dividedBy(100, 4, RoundingMode::HALF_UP);

            // Proporcionálna časť pre tento konkrétny ticker
            $itemInitialValue = $totalInitialValue->multipliedBy($weightMultiplier);
            $itemInvested = $totalInvested->multipliedBy($weightMultiplier);

            $investment = Investment::where('user_id', $plan->user_id)
                ->where('ticker', $ticker)
                ->where('account_id', $plan->account_id)
                ->first();

            if (!$investment) continue;

            $quote = $apiService->getLiveQuote($ticker);
            $currentPrice = BigDecimal::of($quote['price'] ?? 1);
            
            if ($currentPrice->isZero()) continue;

            $quantity = $itemInitialValue->dividedBy($currentPrice, 8, RoundingMode::DOWN);

            if ($quantity->isZero()) continue;

            $pricePerUnit = $itemInvested->isZero() 
                ? $currentPrice 
                : $itemInvested->dividedBy($quantity, 4, RoundingMode::HALF_UP);

            InvestmentTransaction::create([
                'user_id' => auth()->id(),
                'investment_id' => $investment->id,
                'investment_plan_id' => $plan->id,
                'type' => TransactionType::BUY,
                'quantity' => (string) $quantity,
                'price_per_unit' => (string) $pricePerUnit,
                'commission' => '0',
                'currency_id' => $plan->currency_id,
                'exchange_rate' => CurrencyService::getLiveRateById($plan->currency_id),
                'transaction_date' => $data['start_date'] ?? now(),
                'notes' => "Počiatočný stav: {$weightStr}% podiel plánu",
                'subtract_from_broker' => false, // Initial state should not subtract from broker usually
            ]);
        }
    }
}
