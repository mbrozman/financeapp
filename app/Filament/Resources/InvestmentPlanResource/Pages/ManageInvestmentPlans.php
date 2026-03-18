<?php

namespace App\Filament\Resources\InvestmentPlanResource\Pages;

use App\Filament\Resources\InvestmentPlanResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;
use App\Models\Investment;
use App\Models\InvestmentPlan;
use App\Models\InvestmentTransaction;
use App\Models\Currency;
use App\Enums\TransactionType;
use App\Services\StockApiService;
use App\Services\CurrencyService;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class ManageInvestmentPlans extends ManageRecords
{
    protected static string $resource = InvestmentPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->using(function (array $data, string $model): InvestmentPlan {
                    $ticker = $data['ticker'] ?? null;
                    $userId = auth()->id();

                    // 1. Zabezpečenie existencie investície
                    if ($ticker) {
                        $apiService = app(StockApiService::class);
                        $quote = $apiService->getLiveQuote($ticker);

                        $investment = Investment::firstOrCreate(
                            ['user_id' => $userId, 'ticker' => $ticker],
                            [
                                'name' => $quote['name'] ?? $ticker,
                                'currency_id' => Currency::where('code', $quote['currency'] ?? 'EUR')->first()?->id ?? $data['currency_id'],
                                'account_id' => $data['account_id'],
                                'investment_category_id' => 2, // akcie
                                'broker' => \App\Models\Account::find($data['account_id'])?->name ?? 'XTB',
                                'asset_type' => 'ETF',
                                'current_price' => $quote['price'] ?? '0',
                            ]
                        );
                        $data['investment_id'] = $investment->id;
                    }

                    // 2. Záloha pôvodných dát pre transakciu (pred vyčistením)
                    $originalData = $data;

                    // 3. Očistenie dát od dočasných polí pre DB
                    $dbFields = [
                        'user_id', 'investment_id', 'account_id', 'amount', 
                        'currency_id', 'frequency', 'next_run_date', 'is_active'
                    ];
                    $cleanData = array_intersect_key($data, array_flip($dbFields));
                    $cleanData['user_id'] = $userId;

                    // 4. Vytvorenie plánu
                    $record = InvestmentPlan::create($cleanData);

                    // 5. Spracovanie počiatočného stavu
                    if ($record && ($originalData['use_initial_state'] ?? false)) {
                        $this->createInitialTransaction($record, $originalData);
                    }

                    return $record;
                }),
        ];
    }

    protected function createInitialTransaction(InvestmentPlan $plan, array $data): void
    {
        $ticker = $plan->investment->ticker;
        $apiService = app(StockApiService::class);
        $quote = $apiService->getLiveQuote($ticker);
        $currentPrice = BigDecimal::of($quote['price'] ?? 1);
        
        if ($currentPrice->isZero()) return;

        // Hodnota / Aktuálna Cena = Počet kusov
        $initialValue = BigDecimal::of($data['initial_total_value'] ?? 0);
        $quantity = $initialValue->dividedBy($currentPrice, 8, RoundingMode::DOWN);

        if ($quantity->isZero()) return;

        // Investovaná suma / Kusy = Priemerná nákupka
        $invested = BigDecimal::of($data['initial_invested_amount'] ?? 0);
        $pricePerUnit = $invested->dividedBy($quantity, 4, RoundingMode::HALF_UP);

        InvestmentTransaction::create([
            'user_id' => auth()->id(),
            'investment_id' => $plan->investment_id,
            'investment_plan_id' => $plan->id,
            'type' => TransactionType::BUY,
            'quantity' => (string) $quantity,
            'price_per_unit' => (string) $pricePerUnit,
            'commission' => '0',
            'currency_id' => $plan->currency_id,
            'exchange_rate' => CurrencyService::getLiveRateById($plan->currency_id),
            'transaction_date' => $data['start_date'] ?? now(),
            'notes' => 'Počiatočný stav pri založení plánu',
        ]);
    }
}
