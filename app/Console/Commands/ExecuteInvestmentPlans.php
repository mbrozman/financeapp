<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\InvestmentPlan;
use App\Models\InvestmentTransaction;
use App\Services\StockApiService;
use App\Services\CurrencyService;
use App\Enums\TransactionType;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExecuteInvestmentPlans extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'investments:execute-plans';
    protected $description = 'Vykoná naplánované investičné nákupy (autoinvest)';

    public function handle(StockApiService $apiService)
    {
        $this->info("Spúšťam spracovanie investičných plánov...");

        $hasPlans = false;

        InvestmentPlan::where('is_active', true)
            ->where('next_run_date', '<=', now()->toDateString())
            ->chunkById(50, function ($plans) use ($apiService, &$hasPlans) {
                if ($plans->isNotEmpty()) {
                    $hasPlans = true;
                }
                foreach ($plans as $plan) {
                    $this->processPlan($plan, $apiService);
                }
            });

        if (!$hasPlans) {
            $this->info("Žiadne plány na spustenie.");
            return;
        }

        $this->info("Spracovanie dokončené.");
    }

    protected function processPlan(InvestmentPlan $plan, StockApiService $apiService)
    {
        $this->info("Spracovávam plán ID: {$plan->id} ({$plan->amount} {$plan->currency->code})");

        try {
            DB::transaction(function () use ($plan, $apiService) {
                // 0. ZÍSKANIE ZÁMKU A OVERENIE (Idempotencia)
                $lockedPlan = InvestmentPlan::where('id', $plan->id)
                    ->lockForUpdate()
                    ->first();

                if (!$lockedPlan || $lockedPlan->next_run_date->gt(now())) {
                    $this->warn("Plán {$plan->id} už bol pravdepodobne spracovaný súbežným procesom. Preskakujem.");
                    return;
                }

                $items = $plan->items;
                if ($items->isEmpty()) {
                    throw new \Exception("Plán nemá žiadne priradené aktíva (položky).");
                }

                foreach ($items as $item) {
                    $investment = $item->investment;
                    $weight = (string) $item->weight;
                    
                    // Podiel sumy pre toto aktívum
                    $itemAmountString = (string) BigDecimal::of((string)$plan->amount)
                        ->multipliedBy($weight)
                        ->dividedBy(100, 4, RoundingMode::HALF_UP);

                    $this->info("  -> Nákup {$investment->ticker} (Podiel {$weight}%, Suma: {$itemAmountString})");

                    // 1. ZÍSKANIE CENY
                    $quote = $apiService->getLiveQuote($investment->ticker);
                    if (!$quote || !isset($quote['price'])) {
                        $this->error("    ❌ Nepodarilo sa získať cenu pre {$investment->ticker}. Preskakujem túto položku.");
                        continue;
                    }

                    $currentPrice = BigDecimal::of($quote['price']);
                    
                    // 2. PREPOČET SUMY DO MENY INVESTÍCIE (ak je iná)
                    $investedAmountInNativeString = CurrencyService::convert(
                        $itemAmountString,
                        $plan->currency_id,
                        $investment->currency_id
                    );

                    // 3. VÝPOČET KUSOV
                    $quantity = BigDecimal::of($investedAmountInNativeString)
                        ->dividedBy($currentPrice, 8, RoundingMode::DOWN);

                    if ($quantity->isZero()) {
                        $this->warn("    ⚠️ Suma {$itemAmountString} je príliš nízka na nákup {$investment->ticker}.");
                        continue;
                    }

                    // 4. VYTVORENIE TRANSAKCIE
                    InvestmentTransaction::create([
                        'user_id' => $plan->user_id,
                        'investment_id' => $investment->id,
                        'type' => TransactionType::BUY,
                        'quantity' => (string) $quantity,
                        'price_per_unit' => (string) $currentPrice,
                        'commission' => '0',
                        'currency_id' => $investment->currency_id,
                        'exchange_rate' => CurrencyService::getLiveRateById($investment->currency_id),
                        'transaction_date' => now(),
                        'investment_plan_id' => $plan->id,
                        'notes' => "Automatický nákup ({$weight}%)",
                    ]);

                    $this->info("    ✅ Nakúpené: {$quantity} ks");
                }

                // 5. VYTVORENIE CASH TRANSAKCIE (Deduction from Account)
                if ($plan->account_id) {
                    \App\Models\Transaction::create([
                        'user_id' => $plan->user_id,
                        'account_id' => $plan->account_id,
                        'amount' => -abs($plan->amount),
                        'currency_id' => $plan->currency_id,
                        'transaction_date' => now(),
                        'type' => 'transfer', // Používame 'transfer' pre čistý Cashflow, ale so započítaním do Budgetu
                        'description' => "Investičný nákup: {$plan->name}",
                        'category_id' => $plan->category_id,
                    ]);
                    $this->info("  -> Hotovosť odpočítaná z účtu: {$plan->account->name}");
                }

                // 6. UPDATE PLÁNU (Next Run Date)
                $nextDate = match ($plan->frequency) {
                    'daily' => $plan->next_run_date->addDay(),
                    'weekly' => $plan->next_run_date->addWeek(),
                    'monthly' => $plan->next_run_date->addMonth(),
                    default => $plan->next_run_date->addMonth(),
                };

                $plan->update([
                    'next_run_date' => $nextDate,
                ]);
            });

        } catch (\Exception $e) {
            $this->error("❌ Chyba pri spracovaní plánu ID {$plan->id}: " . $e->getMessage());
            Log::error("Autoinvest Error [Plan {$plan->id}]: " . $e->getMessage());
        }
    }
}
