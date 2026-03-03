<?php

namespace App\Services;

use App\Models\Currency;
use Exception;
use Illuminate\Support\Facades\Log;

class CurrencyService
{
    /**
     * Prepočíta sumu do EUR.
     * Priorita 1: Historický kurz (ak existuje v transakcii)
     * Priorita 2: Aktuálny kurz z tabuľky currencies
     */
    public static function convertToEur(float $amount, ?int $currencyId, ?float $historicalRate = null): float
    {
        // 1. Ak je mena už EUR (predpokladáme ID 1), suma sa nemení
        if ($currencyId === 1) return $amount;

        // 2. Ak máme historický kurz (z transakcie), použijeme ho
        if ($historicalRate && $historicalRate > 0) {
            return $amount / $historicalRate;
        }

        // 3. Ak nemáme historický kurz, vytiahneme aktuálny z DB
        $currency = Currency::find($currencyId);
        
        if (!$currency || $currency->exchange_rate <= 0) {
            Log::error("CurrencyService Error: Chýba kurz pre menu ID: {$currencyId}");
            // Vrátime sumu nezmenenú, aby sa aplikácia nezrútila, ale zaprotokolujeme chybu
            return $amount; 
        }

        return $amount / (float)$currency->exchange_rate;
    }

    /**
     * Vráti aktuálny kurz podľa kódu (pre Create nákup)
     */
    public static function getLiveRate(?string $code): float
    {
        if (!$code || $code === 'EUR') return 1.0;

        $currency = Currency::where('code', $code)->first();
        return ($currency && $currency->exchange_rate > 0) ? (float)$currency->exchange_rate : 1.0;
    }

    public static function getRate(?string $code): float
{
    return self::getLiveRate($code);
}
}