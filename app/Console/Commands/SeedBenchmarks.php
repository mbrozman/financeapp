<?php

namespace App\Console\Commands;

use App\Models\Investment;
use App\Models\InvestmentPriceHistory;
use App\Services\StockApiService;
use Illuminate\Console\Command;
use Scheb\YahooFinanceApi\ApiClient;
use Scheb\YahooFinanceApi\ApiClientFactory;

class SeedBenchmarks extends Command
{
    protected $signature = 'app:seed-benchmarks {--days=730 : Počet dní histórie (default: 2 roky)}';
    protected $description = 'Vytvorí benchmark záznamy (SPY, QQQ) a stiahne ich cenovú históriu';

    protected array $benchmarks = [
        'SPY' => 'SPDR S&P 500 ETF Trust',
        'QQQ' => 'Invesco QQQ Trust (Nasdaq 100)',
    ];

    public function handle(StockApiService $api): int
    {
        $days = (int) $this->option('days');
        $this->info("🔧 Seedujem benchmark dáta (posledných {$days} dní)...\n");

        foreach ($this->benchmarks as $ticker => $name) {
            $this->processBenchmark($ticker, $name, $api, $days);
        }

        $this->newLine();
        $this->info('✅ Hotovo! Benchmark záznamy sú pripravené.');
        $this->info('   Graf "Výkonnosť vs Benchmarky" by mal teraz zobrazovať SPY a QQQ.');

        return Command::SUCCESS;
    }

    private function processBenchmark(string $ticker, string $name, StockApiService $api, int $days): void
    {
        $this->line("📊 Spracúvam: <fg=cyan>{$ticker}</> – {$name}");

        // 1. Nájdi alebo vytvor benchmark Investment záznam
        $investment = Investment::withoutGlobalScopes()
            ->where('ticker', $ticker)
            ->where('is_benchmark', true)
            ->first();

        if (!$investment) {
            $investment = Investment::withoutGlobalScopes()->create([
                'ticker'        => $ticker,
                'name'          => $name,
                'is_benchmark'  => true,
                'is_archived'   => false,
                'user_id'       => null,
                'account_id'    => null,
                'total_quantity'=> 0,
                'average_buy_price' => 0,
                'current_price' => 0,
            ]);
            $this->line("   ✨ Nový záznam vytvorený (ID: {$investment->id})");
        } else {
            $this->line("   ℹ️  Záznam existuje (ID: {$investment->id})");
        }

        // 2. Stiahni cenovú históriu
        $this->line("   ⬇️  Sťahujem históriu ({$days} dní)...");

        try {
            $result = $api->downloadHistory($investment, $days);

            if ($result) {
                $count = InvestmentPriceHistory::where('investment_id', $investment->id)->count();
                $this->line("   ✅ Hotovo – {$count} záznamov v DB");
            } else {
                $this->warn("   ⚠️  Stiahnutie histórie zlyhalo pre {$ticker}");
            }
        } catch (\Exception $e) {
            $this->error("   ❌ Chyba: " . $e->getMessage());
        }

        // 3. Aktualizuj aktuálnu cenu
        $liveData = $api->getLiveQuote($ticker);
        if ($liveData) {
            $investment->update([
                'current_price'    => $liveData['price'],
                'last_price_update' => now(),
            ]);

            InvestmentPriceHistory::updateOrCreate(
                ['investment_id' => $investment->id, 'recorded_at' => now()->format('Y-m-d')],
                ['price' => $liveData['price']]
            );

            $this->line("   💰 Aktuálna cena: {$liveData['price']} USD");
        }

        $this->newLine();
    }
}
