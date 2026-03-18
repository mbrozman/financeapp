<?php

namespace App\Services;

use App\Models\Currency;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class CurrencyService
{
    /**
     * Univerzálny a presný prepočet do EUR
     * Vracia STRING, aby sa zachovala presnosť pre ďalšie výpočty.
     * Kurz očakávame v tvare násobiteľa: "Koľko EUR stojí 1 jednotka cudzej meny" (napr. 0.92 EUR za 1 USD)
     */
    public static function convertToEur(string|float|null $amount, ?int $currencyId, ?float $historicalRate = null): string
    {
        if (!$amount || $amount == 0) return '0.0000';
        
        // 1. Ak je mena EUR (ID 1), vrátime sumu s presnosťou na 4 miesta
        if ($currencyId === 1) {
            return (string) BigDecimal::of($amount)->toScale(4, RoundingMode::HALF_UP);
        }

        // 2. Inicializujeme sumu cez BigDecimal
        $amountBD = BigDecimal::of($amount);
        
        // 3. Zistíme kurz (historický alebo aktuálny)
        $rawRate = self::resolveRateById($currencyId, $historicalRate);

        if ($rawRate <= 0) return (string) $amountBD->toScale(4, RoundingMode::HALF_UP);

        // 4. PREVOD KURZU NA BIGDECIMAL
        $rateBD = BigDecimal::of($rawRate);

        // 5. Presné NÁSOBENIE: Suma * Kurz (napr. 100 USD * 0.92 = 92 EUR)
        return (string) $amountBD->multipliedBy($rateBD)->toScale(4, RoundingMode::HALF_UP);
    }

    /**
     * Flexibilný prepočet medzi ľubovoľnými menami cez EUR ako základňu.
     */
    public static function convert(string|float|null $amount, ?int $fromCurrencyId, ?int $toCurrencyId, ?float $historicalRate = null): string
    {
        if (!$amount || $amount == 0) return '0.0000';
        if ($fromCurrencyId === $toCurrencyId) return (string) BigDecimal::of($amount)->toScale(4, RoundingMode::HALF_UP);

        // 1. Prevedieme "Z meny" do EUR (násobením)
        // Ak ideme EUR -> USD, tak $fromCurrencyId je 1 (EUR), $toCurrencyId je napr. 3 (USD)
        // convertToEur pri $currencyId = 1 len vráti pôvodnú sumu.
        $eurValue = self::convertToEur($amount, $fromCurrencyId, $historicalRate);

        // 2. Ak cieľová mena je EUR, končíme
        if ($toCurrencyId === 1) return $eurValue;

        // 3. Prevedieme z EUR do "Cieľovej meny" (DELENÍM kurzom, keďže kurz je k EUR)
        // Ak ideme EUR -> USD a vieme historický kurz (napr. 0.92), tak suma_eur / 0.92 = suma_usd
        $rate = self::getLiveRateById($toCurrencyId);

        if ($rate <= 0) return $eurValue;

        return (string) BigDecimal::of($eurValue)->dividedBy($rate, 4, RoundingMode::HALF_UP);
    }

    /**
     * Vráti aktuálny kurz podľa ID
     */
    public static function getLiveRateById(?int $id): float
    {
        if (!$id || $id === 1) return 1.0;

        $currency = Currency::find($id);
        
        return self::normalizeRate($currency?->exchange_rate);
    }

    /**
     * Alias pre getRate (podľa kódu meny, napr. 'USD')
     */
    public static function getRate(?string $code): float
    {
        if (!$code || $code === 'EUR') return 1.0;

        $currency = Currency::where('code', $code)->first();
        
        return self::normalizeRate($currency?->exchange_rate);
    }

    /**
     * Druhý alias pre spätnú kompatibilitu
     */
    public static function getLiveRate(?string $code): float
    {
        return self::getRate($code);
    }

    private static function resolveRateById(?int $currencyId, ?float $historicalRate = null): float
    {
        if ($historicalRate !== null) {
            $normalizedHistorical = self::normalizeRate($historicalRate);
            if ($normalizedHistorical > 0) {
                return $normalizedHistorical;
            }
        }

        return self::getLiveRateById($currencyId);
    }

    private static function normalizeRate(mixed $rate): float
    {
        $rateFloat = (float) ($rate ?? 0);
        return $rateFloat > 0 ? $rateFloat : 1.0;
    }
}
