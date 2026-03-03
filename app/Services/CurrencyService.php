<?php

namespace App\Services;

use App\Models\Currency;

class CurrencyService
{
    /**
     * Univerzálny prepočet do EUR
     */
    public static function convertToEur(float $amount, ?int $currencyId, ?float $historicalRate = null): float
    {
        if ($currencyId === 1) return $amount;

        if ($historicalRate && $historicalRate > 0) {
            return $amount / $historicalRate;
        }

        $currency = Currency::find($currencyId);
        $rate = ($currency && $currency->exchange_rate > 0) ? (float)$currency->exchange_rate : 1.0;

        return $amount / $rate;
    }

    /**
     * Vráti aktuálny kurz podľa kódu meny (napr. 'USD')
     */
    public static function getRate(?string $code): float
    {
        if (!$code || $code === 'EUR') return 1.0;

        $currency = Currency::where('code', $code)->first();
        
        return ($currency && $currency->exchange_rate > 0) ? (float)$currency->exchange_rate : 1.0;
    }

    /**
     * Alias pre getRate (pre spätnú kompatibilitu kódu)
     */
    public static function getLiveRate(?string $code): float
    {
        return self::getRate($code);
    }

    /**
     * Vráti symbol meny
     */
    public static function getSymbol(?int $currencyId): string
    {
        return Currency::find($currencyId)?->symbol ?? '€';
    }
}