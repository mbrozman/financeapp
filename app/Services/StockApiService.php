<?php

namespace App\Services;

use Scheb\YahooFinanceApi\ApiClient;
use Scheb\YahooFinanceApi\ApiClientFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache; // PRIDANÉ
use App\Models\Investment;
use App\Models\InvestmentPriceHistory;
use Brick\Math\BigDecimal; // PRIDANÉ

class StockApiService
{
    protected $client;

    public function __construct()
    {
        $this->client = ApiClientFactory::createApiClient([
            'verify' => false,
            'timeout' => 10.0,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
            ],
        ]);
    }

    /**
     * 1. VYHĽADÁVANIE TICKEROV
     * Používame User-Agent, aby nás Yahoo nepovažovalo za bota.
     */
    public function searchSymbols(string $query): array
    {
        if (empty($query)) return [];

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36'
            ])->get("https://query1.finance.yahoo.com/v1/finance/search", [
                'q' => $query,
                'quotesCount' => 10,
            ]);

            if ($response->failed()) return [];

            $quotes = $response->json('quotes') ?? [];
            $results = [];

            foreach ($quotes as $quote) {
                $symbol = $quote['symbol'] ?? '';
                $name = $quote['longname'] ?? $quote['shortname'] ?? $symbol;
                if ($symbol) {
                    $results[$symbol] = "{$symbol} - {$name}";
                }
            }

            return $results;
        } catch (\Exception $e) {
            Log::error("Yahoo Search Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 2. ZÍSKANIE ŽIVEJ CENY (S KEŠOVANÍM NA 15 MINÚT)
     */
    public function getLiveQuote(string $ticker): ?array
    {
        $cacheKey = "stock_price_{$ticker}";

        // Ak máme cenu v pamäti, vrátime ju. Ak nie, stiahneme a uložíme na 15 min.
        return Cache::remember($cacheKey, now()->addMinutes(15), function () use ($ticker) {
            try {
                $quote = $this->client->getQuote($ticker);
                
                if (!$quote) return null;

                // Všetky sumy vraciame ako STRING pre BigDecimal
                return [
                    'price' => (string) $quote->getRegularMarketPrice(),
                    'change_percent' => (string) $quote->getRegularMarketChangePercent(),
                    'name' => $quote->getLongName() ?? $quote->getShortName() ?? $ticker,
                ];
            } catch (\Exception $e) {
                Log::error("Yahoo Live Quote Error for {$ticker}: " . $e->getMessage());
                return null;
            }
        });
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

            if (!$history) return false;

            foreach ($history as $data) {
                InvestmentPriceHistory::updateOrCreate(
                    [
                        'investment_id' => $investment->id,
                        'recorded_at' => $data->getDate()->format('Y-m-d'),
                    ],
                    [
                        // Cena ako string pre zachovanie presnosti v DB
                        'price' => (string) $data->getClose(),
                    ]
                );
            }
            return true;
        } catch (\Exception $e) {
            Log::error("Yahoo History Error for {$investment->ticker}: " . $e->getMessage());
            return false;
        }
    }
}