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

        $plans = InvestmentPlan::where('is_active', true)
            ->where('next_run_date', '<=', now()->toDateString())
            ->get();

        if ($plans->isEmpty()) {
            $this->info("Žiadne plány na spustenie.");
            return;
        }

        foreach ($plans as $plan) {
            $this->processPlan($plan, $apiService);
        }

        $this->info("Spracovanie dokončené.");
    }

    protected function processPlan(InvestmentPlan $plan, StockApiService $apiService)
    {
        $this->info("Spracovávam plán pre: {$plan->investment->ticker} ({$plan->amount} {$plan->currency->code})");

        try {
            DB::beginTransaction();

            // 1. ZÍSKANIE CENY
            $quote = $apiService->getLiveQuote($plan->investment->ticker);
            if (!$quote || !isset($quote['price'])) {
                throw new \Exception("Nepodarilo sa získať cenu pre {$plan->investment->ticker}");
            }

            $currentPrice = BigDecimal::of($quote['price']);
            
            // 2. PREPOČET SUMY DO MENY INVESTÍCIE (ak je iná)
            // Ak plan->amount je v EUR a investícia v USD, musíme vedieť, koľko USD ideme investovať
            $investedAmountInNative = CurrencyService::convert(
                $plan->amount,
                $plan->currency_id,
                $plan->investment->currency_id
            );

            // 3. VÝPOČET KUSOV
            $quantity = BigDecimal::of($investedAmountInNative)
                ->dividedBy($currentPrice, 8, RoundingMode::DOWN);

            if ($quantity->isZero()) {
                throw new \Exception("Suma je príliš nízka na nákup aspoň malej časti.");
            }

            // 4. VYTVORENIE TRANSAKCIE
            $tx = InvestmentTransaction::create([
                'user_id' => $plan->user_id,
                'investment_id' => $plan->investment_id,
                'type' => TransactionType::BUY,
                'quantity' => (string) $quantity,
                'price_per_unit' => (string) $currentPrice,
                'commission' => '0', // TODO: Možnosť pridať poplatok do plánu
                'currency_id' => $plan->investment->currency_id,
                'exchange_rate' => CurrencyService::getRateById($plan->investment->currency_id),
                'transaction_date' => now(),
                'notes' => 'Automatický nákup (Autoinvest)',
            ]);

            // 5. UPDATE BALANCE NA ÚČTE
            // Musíme odpočítať plan->amount v mene účtu
            $account = $plan->account;
            $amountInAccountCurrency = CurrencyService::convert(
                $plan->amount,
                $plan->currency_id,
                $account->currency_id
            );

            $account->decrement('balance', (string) $amountInAccountCurrency);

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

            DB::commit();
            $this->info("✅ Úspešne nakúpené: {$quantity} ks za {$currentPrice}");

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("❌ Chyba pri spracovaní plánu ID {$plan->id}: " . $e->getMessage());
            Log::error("Autoinvest Error [Plan {$plan->id}]: " . $e->getMessage());
        }
    }
}
