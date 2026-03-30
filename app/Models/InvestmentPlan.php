<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InvestmentPlan extends Model
{
    use BelongsToUser, HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'account_id',
        'amount',
        'currency_id',
        'frequency',
        'next_run_date',
        'is_active',
        // Tieto polia nie sú v DB, slúžia na spracovanie pri vytváraní
        'ticker',
        'use_initial_state',
        'initial_total_value',
        'initial_invested_amount',
        'start_date',
    ];

    protected $casts = [
        'next_run_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function items(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(InvestmentPlanItem::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function transactions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(InvestmentTransaction::class);
    }

    public function getTotalInvestedEur(): float
    {
        $total = \Brick\Math\BigDecimal::zero();
        foreach ($this->transactions()->with('currency')->get() as $tx) {
            $amountBase = \Brick\Math\BigDecimal::of($tx->quantity)->multipliedBy($tx->price_per_unit);
            $amountEur = \App\Services\CurrencyService::convertToEur((string)$amountBase, $tx->currency_id, $tx->exchange_rate);
            $total = $total->plus($amountEur);
        }
        return $total->toFloat();
    }

    public function getCurrentMarketValueEur(): float
    {
        $total = \Brick\Math\BigDecimal::zero();
        foreach ($this->transactions()->with('investment')->get() as $tx) {
            $currentPriceEur = \App\Services\CurrencyService::convertToEur($tx->investment?->current_price ?? 0, $tx->investment?->currency_id);
            $valEur = \Brick\Math\BigDecimal::of($tx->quantity)->multipliedBy($currentPriceEur);
            $total = $total->plus($valEur);
        }
        return $total->toFloat();
    }

    public function getTotalGainEur(): float
    {
        return $this->getCurrentMarketValueEur() - $this->getTotalInvestedEur();
    }

    public function getTotalGainPercent(): float
    {
        $invested = $this->getTotalInvestedEur();
        if ($invested <= 0) return 0;
        return ($this->getTotalGainEur() / $invested) * 100;
    }

    public function getMarketValueForItem(InvestmentPlanItem $item): float
    {
        $total = \Brick\Math\BigDecimal::zero();
        $transactions = $this->transactions()
            ->where('investment_id', $item->investment_id)
            ->with('investment')
            ->get();

        foreach ($transactions as $tx) {
            $currentPriceEur = \App\Services\CurrencyService::convertToEur($tx->investment?->current_price ?? 0, $tx->investment?->currency_id);
            $valEur = \Brick\Math\BigDecimal::of($tx->quantity)->multipliedBy($currentPriceEur);
            $total = $total->plus($valEur);
        }
        return $total->toFloat();
    }

    public function getGainPercentForItem(InvestmentPlanItem $item): float
    {
        $invested = \Brick\Math\BigDecimal::zero();
        $transactions = $this->transactions()
            ->where('investment_id', $item->investment_id)
            ->with(['investment', 'currency'])
            ->get();

        foreach ($transactions as $tx) {
            $amountBase = \Brick\Math\BigDecimal::of($tx->quantity)->multipliedBy($tx->price_per_unit);
            $amountEur = \App\Services\CurrencyService::convertToEur((string)$amountBase, $tx->currency_id, $tx->exchange_rate);
            $invested = $invested->plus($amountEur);
        }

        if ($invested->isZero()) return 0;

        $marketValue = $this->getMarketValueForItem($item);
        $gain = $marketValue - $invested->toFloat();
        
        return ($gain / $invested->toFloat()) * 100;
    }

    public function getGainColor(): string
    {
        $gain = $this->getTotalGainEur();
        if ($gain > 0) return 'success';
        if ($gain < 0) return 'danger';
        return 'gray';
    }

    public function getGainColorForItem(InvestmentPlanItem $item): string
    {
        $gain = $this->getMarketValueForItem($item) - $this->getItemInvestedEur($item);
        if ($gain > 0) return 'success';
        if ($gain < 0) return 'danger';
        return 'gray';
    }

    private function getItemInvestedEur(InvestmentPlanItem $item): float
    {
        $invested = \Brick\Math\BigDecimal::zero();
        $transactions = $this->transactions()
            ->where('investment_id', $item->investment_id)
            ->with(['investment', 'currency'])
            ->get();

        foreach ($transactions as $tx) {
            $amountBase = \Brick\Math\BigDecimal::of($tx->quantity)->multipliedBy($tx->price_per_unit);
            $amountEur = \App\Services\CurrencyService::convertToEur((string)$amountBase, $tx->currency_id, $tx->exchange_rate);
            $invested = $invested->plus($amountEur);
        }
        return $invested->toFloat();
    }
}
