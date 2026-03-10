<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Investment;
use Illuminate\Support\Facades\Http;

class FetchInvestmentClassification extends Command
{
    protected $signature = 'investments:fetch-classification';
    protected $description = 'Fetches Sector, Industry, Country, and Type for existing investments from Yahoo Finance';

    public function handle()
    {
        $investments = Investment::whereNull('sector')->orWhereNull('asset_type')->get();
        if ($investments->isEmpty()) {
            $this->info("Všetky investície už majú priradenú klasifikáciu.");
            return;
        }

        $this->info("Spúšťam aktualizáciu klasifikácie pre {$investments->count()} investícií...");

        foreach ($investments as $investment) {
            $this->info("Sťahujem údaje pre: {$investment->ticker} ({$investment->name})");
            
            // Základná klasifikácia pre krypto a ETF podľa tickera, ak prístup na Yahoo zlyhá
            if (str_ends_with(strtoupper($investment->ticker), '-USD')) {
                $investment->update([
                    'asset_type' => 'Crypto',
                    'sector' => 'Cryptocurrency',
                    'country' => 'Global'
                ]);
                $this->line("- Nastavené ako Krypto na základe tickera.");
                continue;
            }

            // Skúsime jednoduchý scraping Yahoo Finance
            try {
                $response = Http::withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0 Safari/537.36'
                ])->get("https://finance.yahoo.com/quote/{$investment->ticker}/profile");

                if ($response->successful()) {
                    $html = $response->body();
                    
                    // Jednoduchý regex na vydolovanie dát z HTML (JSON v stránke)
                    $sector = null;
                    $industry = null;
                    $country = null;
                    $assetType = 'Equity'; // Default

                    if (preg_match('/"sector":"([^"]+)"/', $html, $matches)) {
                        $sector = $matches[1];
                    }
                    if (preg_match('/"industry":"([^"]+)"/', $html, $matches)) {
                        $industry = $matches[1];
                    }
                    if (preg_match('/"country":"([^"]+)"/', $html, $matches)) {
                        $country = $matches[1];
                    }
                    if (preg_match('/"quoteType":"(ETF)"/', $html, $matches)) {
                        $assetType = 'ETF';
                        if (!$sector) $sector = 'Index Fund';
                    }

                    if ($sector || $country) {
                        $investment->update([
                            'sector' => $sector,
                            'industry' => $industry,
                            'country' => $country,
                            'asset_type' => $assetType
                        ]);
                        $this->line("- Úspešne aktualizované z Yahoo: Typ=$assetType, Sektor=$sector, Krajina=$country");
                    } else {
                        $this->warn("- Nepodarilo sa nájsť profilové dáta na stránke.");
                    }
                } else {
                    $this->error("- HTTP Chyba: " . $response->status());
                }
            } catch (\Exception $e) {
                $this->error("- Výnimka: " . $e->getMessage());
            }

            // Pauza aby nás Yahoo nezablokovalo
            sleep(1);
        }

        $this->info("Hotovo! Klasifikácia investícií bola dokončená.");
    }
}
