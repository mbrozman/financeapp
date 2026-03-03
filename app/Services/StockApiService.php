<?php

namespace App\Services;

use Scheb\YahooFinanceApi\ApiClient;
use Scheb\YahooFinanceApi\ApiClientFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Investment;
use App\Models\InvestmentPriceHistory;
use Illuminate\Support\Facades\Cache;

class StockApiService
{
    protected $client;

    public function __construct()
    {
        // Nastavenie klienta s ochranou proti blokovaniu a SSL chybám
        $this->client = ApiClientFactory::createApiClient([
            'verify' => false,
            'timeout' => 10.0,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
            ],
        ]);
    }

    /**
     * 1. VYHĽADÁVANIE TICKEROV (Tento kód ti chýbal)
     */
 public function searchSymbols(string $query): array
{
    if (empty($query)) return [];

    try {
        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36'
        ])->get("https://query1.finance.yahoo.com/v1/finance/search", [
            'q' => $query,
            'quotesCount' => 10,
        ]);

        $quotes = $response->json('quotes') ?? [];
        $results = [];

        foreach ($quotes as $quote) {
            $symbol = $quote['symbol'] ?? '';
            // TU JE ZMENA: Získame čo najpresnejší názov
            $name = $quote['longname'] ?? $quote['shortname'] ?? $symbol;
            
            if ($symbol) {
                // Uložíme to tak, aby sme v Selecte videli "AAPL - Apple Inc."
                // Ale vrátime to tak, aby sme z toho vedeli vytiahnuť ten názov
                $results[$symbol] = "{$symbol} - {$name}";
            }
        }

        return $results;
    } catch (\Exception $e) {
        return [];
    }
}

    /**
     * 2. ZÍSKANIE ŽIVEJ CENY
     */
    public function getLiveQuote(string $ticker): ?array
{
    try {
        $quote = $this->client->getQuote($ticker);
        
        if (!$quote) return null;

        return [
            'price' => (float) $quote->getRegularMarketPrice(),
            'change_percent' => (float) $quote->getRegularMarketChangePercent(),
            // PRIDÁVAME NÁZOV (Skúsime dlhý, ak nie je, tak krátky názov)
            'name' => $quote->getLongName() ?? $quote->getShortName() ?? $ticker,
        ];
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error("Yahoo Live Quote Error: " . $e->getMessage());
        return null;
    }
}

    /**
     * 3. SŤAHOVANIE HISTÓRIE PRE GRAF
     */
    public function downloadHistory(Investment $investment, int $days = 365)
    {
        try {
            $startDate = new \DateTime("-{$days} days");
            $endDate = new \DateTime("now");

            $history = $this->client->getHistoricalQuoteData(
                $investment->ticker,
                ApiClient::INTERVAL_1_DAY,
                $startDate,
                $endDate
            );

            foreach ($history as $data) {
                InvestmentPriceHistory::updateOrCreate(
                    [
                        'investment_id' => $investment->id,
                        'recorded_at' => $data->getDate()->format('Y-m-d'),
                    ],
                    [
                        'price' => $data->getClose(),
                    ]
                );
            }
            return true;
        } catch (\Exception $e) {
            Log::error("History error: " . $e->getMessage());
            return false;
        }
    }
}