<?php

namespace App\Services;

use App\Models\Currency;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\Log;

class CurrencyService
{
    /**
     * Univerzálny a presný prepočet do EUR
     */
    public static function convertToEur(string|float|null $amount, ?int $currencyId, ?float $historicalRate = null): string
    {
        if (!$amount || $amount == 0) return '0';
        
        // 1. Ak je mena EUR (ID 1), vrátime sumu ako string
        if ($currencyId === 1) return (string) $amount;

        // 2. Inicializujeme sumu cez BigDecimal
        $amountBD = BigDecimal::of($amount);
        
        // 3. Zistíme kurz (historický alebo aktuálny)
        $rate = ($historicalRate && $historicalRate > 0) 
            ? $historicalRate 
            : self::getLiveRateById($currencyId);

        if ($rate <= 0) return (string) $amount;

        // 4. Presné delenie na 4 desatinné miesta
        return (string) $amountBD->dividedBy($rate, 4, RoundingMode::HALF_UP);
    }

    /**
     * Pomocná metóda na získanie kurzu podľa ID
     */
    public static function getLiveRateById(?int $id): float
    {
        if (!$id || $id === 1) return 1.0;

        $currency = Currency::find($id);
        
        return ($currency && $currency->exchange_rate > 0) ? (float)$currency->exchange_rate : 1.0;
    }

    /**
     * Pôvodná metóda podľa kódu (pre spätnú kompatibilitu)
     */
    public static function getLiveRate(?string $code): float
    {
        if (!$code || $code === 'EUR') return 1.0;

        $currency = Currency::where('code', $code)->first();
        
        return ($currency && $currency->exchange_rate > 0) ? (float)$currency->exchange_rate : 1.0;
    }
}