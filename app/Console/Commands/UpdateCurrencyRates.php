<?php

namespace App\Console\Commands;

use App\Models\Currency;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class UpdateCurrencyRates extends Command
{
    // Týmto príkazom to budeme spúšťať v termináli
    protected $signature = 'app:update-rates';

    // Popis príkazu
    protected $description = 'Stiahne aktuálne výmenné kurzy z ECB';

    public function handle()
    {
        $this->info('Aktualizujem kurzy mien...');

        // 1. Zavoláme API (základná mena EUR)
        // Použijeme HTTP klienta, ktorý je v Laraveli zabudovaný
        $response = Http::get('https://api.frankfurter.app/latest?from=EUR');

        if ($response->failed()) {
            $this->error('Nepodarilo sa spojiť s API.');
            return;
        }

        $rates = $response->json('rates');

        // 2. Prejdeme všetky meny, ktoré máme v našej databáze
        $currencies = Currency::where('code', '!=', 'EUR')->get();

        foreach ($currencies as $currency) {
            // Ak API obsahuje kurz pre našu menu, aktualizujeme ju
            if (isset($rates[$currency->code])) {
                $currency->update([
                    'exchange_rate' => $rates[$currency->code],
                ]);
                $this->line("Mena {$currency->code} aktualizovaná na {$rates[$currency->code]}");
            }
        }

        $this->info('Všetky kurzy boli úspešne aktualizované.');
    }
}