<?php

namespace App\Services;

use App\Models\Account;
use App\Models\FinancialPlan;
use App\Models\PortfolioSnapshot;
use App\Models\Transaction;
use Brick\Math\BigDecimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardFinanceService
{
    public static function getYearlyCashflowCacheKey($userId, int $year): string
    {
        return "dashboard_yearly_cashflow_{$userId}_{$year}";
    }

    public function getTotalNetWorth($userId): float
    {
        // 1. Likvidita (Účty)
        $accounts = Account::where('user_id', $userId)->get();
        $totalAccounts = 0;
        foreach ($accounts as $account) {
            $totalAccounts += (float) CurrencyService::convertToEur((string) $account->balance, $account->currency_id);
        }

        // 2. Investície (Trhová hodnota)
        $investments = \App\Models\Investment::where('user_id', $userId)->get();
        $totalInvestments = 0;
        foreach ($investments as $investment) {
            $totalInvestments += (float) $investment->current_market_value_eur;
        }

        return round($totalAccounts + $totalInvestments, 2);
    }

    public function getLiquidityStats($userId): array
    {
        $accounts = Account::with('currency')
            ->where('user_id', $userId)
            ->whereIn('type', ['bank', 'cash', 'reserve'])
            ->get();

        $totalBank = BigDecimal::zero();
        $totalCash = BigDecimal::zero();
        $totalReserve = BigDecimal::zero();

        foreach ($accounts as $account) {
            $eurValueString = CurrencyService::convertToEur((string) $account->balance, $account->currency_id);
            $eurValue = BigDecimal::of($eurValueString);
            
            if ($account->type === 'bank') $totalBank = $totalBank->plus($eurValue);
            elseif ($account->type === 'cash') $totalCash = $totalCash->plus($eurValue);
            elseif ($account->type === 'reserve') $totalReserve = $totalReserve->plus($eurValue);
        }
        
        $totalLiquidity = $totalBank->plus($totalCash)->plus($totalReserve);

        return [
            'total_liquidity' => round($totalLiquidity->toFloat(), 2),
            'total_bank' => round($totalBank->toFloat(), 2),
            'total_cash' => round($totalCash->toFloat(), 2),
            'total_reserve' => round($totalReserve->toFloat(), 2),
        ];
    }

    public function getYearlyCashflow($userId, int $year): array
    {
        return \Illuminate\Support\Facades\Cache::remember(
            self::getYearlyCashflowCacheKey($userId, $year),
            900, // 15 minút caching
            function () use ($userId, $year) {
                $totals = Transaction::select(
                    DB::raw("SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as total_income"),
            DB::raw("SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as total_expense")
        )
            ->where('user_id', $userId)
            ->whereYear('transaction_date', $year)
            ->whereNull('linked_transaction_id')
            ->first();

        $data = Transaction::select(
            DB::raw("date_trunc('month', transaction_date) as month"),
            DB::raw("SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as total_income"),
            DB::raw("SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as total_expense")
        )
            ->where('user_id', $userId)
            ->whereYear('transaction_date', $year)
            ->whereNull('linked_transaction_id')
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $labels = [];
        $incomeValues = [];
        $expenseValues = [];
        $surplusValues = [];

        for ($i = 1; $i <= 12; $i++) {
            $monthDate = Carbon::create($year, $i, 1);
            $labels[] = $monthDate->translatedFormat('M');
            $monthData = $data->first(fn ($item) => Carbon::parse($item->month)->month === $i);
            
            $incomeBD = $monthData && $monthData->total_income !== null ? BigDecimal::of((string) $monthData->total_income)->abs() : BigDecimal::zero();
            $expenseBD = $monthData && $monthData->total_expense !== null ? BigDecimal::of((string) $monthData->total_expense)->abs() : BigDecimal::zero();
            
            $incomeValues[] = round($incomeBD->toFloat(), 2);
            $expenseValues[] = round($expenseBD->toFloat(), 2);
            $surplusValues[] = round($incomeBD->minus($expenseBD)->toFloat(), 2);
        }

        $totalIncomeBD = isset($totals->total_income) && $totals->total_income !== null ? BigDecimal::of((string) $totals->total_income)->abs() : BigDecimal::zero();
        $totalExpenseBD = isset($totals->total_expense) && $totals->total_expense !== null ? BigDecimal::of((string) $totals->total_expense)->abs() : BigDecimal::zero();

                return [
                    'total_income' => round($totalIncomeBD->toFloat(), 2),
                    'total_expense' => round($totalExpenseBD->toFloat(), 2),
                    'total_surplus' => round($totalIncomeBD->minus($totalExpenseBD)->toFloat(), 2),
                    'labels' => $labels,
                    'income_values' => $incomeValues,
                    'expense_values' => $expenseValues,
                    'surplus_values' => $surplusValues,
                ];
            }
        );
    }

    public function getRealityVsPlanSeries($userId, string $filter = 'with_roi'): array
    {
        $plan = FinancialPlan::with('items')->where('user_id', $userId)->first();
        $year = now()->year;
        
        $labels = [];
        $realityValues = [];
        $modelValues = [];

        for ($i = 1; $i <= 12; $i++) {
            $date = Carbon::create($year, $i, 1);
            $labels[] = $date->translatedFormat('M Y');
            $realityValues[$i] = 0;
            $modelValues[$i] = 0;
        }

        // 1. ZÍSKAME AKTUÁLNY NET WORTH (HOTOVOSŤ + INVESTÍCIE)
        $currentNetWorth = $this->getTotalNetWorth($userId);

        // 2. ZÍSKAME CELKOVÚ ZMENU ZA TENTO ROK DOTERAZ (Z TRANSAKCIÍ)
        $totalChangeYTD = Transaction::where('user_id', $userId)
            ->whereYear('transaction_date', $year)
            ->where('transaction_date', '<=', now())
            ->sum(DB::raw("CASE WHEN type = 'income' THEN amount ELSE -amount END"));
        
        $totalChangeYTD = (float) $totalChangeYTD;

        // 3. POČIATOČNÝ BOD (NET WORTH NA ZAČIATKU ROKA)
        $initialWealth = $currentNetWorth - $totalChangeYTD;

        if (!$plan) {
            return [
                'labels' => $labels,
                'reality_values' => array_values($realityValues),
                'model_values' => array_values($modelValues),
                'is_ahead' => true,
            ];
        }

        $startMonth = $plan->created_at->month;
        
        // 4. MESAČNÉ POHYBY
        $monthlyData = Transaction::select(
            DB::raw("EXTRACT(MONTH FROM transaction_date) as month"),
            DB::raw("SUM(CASE WHEN type = 'income' THEN amount ELSE -amount END) as net_change")
        )
            ->where('user_id', $userId)
            ->whereYear('transaction_date', $year)
            ->groupBy('month')
            ->get()
            ->keyBy('month');

        $monthlySavingsIdeal = $plan->getMonthlySavingsAmount()->toFloat();
        
        $accumulatedReality = BigDecimal::of((string) $initialWealth);
        $accumulatedModel = BigDecimal::of((string) $initialWealth);

        for ($i = 1; $i <= 12; $i++) {
            // Reality
            $data = $monthlyData->get($i);
            $changeBD = $data && $data->net_change !== null ? BigDecimal::of((string)$data->net_change) : BigDecimal::zero();
            
            // Model (Plánujeme rast od začiatku plánu, predošlé mesiace kopírujú realitu alebo zostávajú na štarte)
            $planChangeBD = ($i >= $startMonth) ? BigDecimal::of((string)$monthlySavingsIdeal) : BigDecimal::zero();

            $accumulatedReality = $accumulatedReality->plus($changeBD);
            $accumulatedModel = $accumulatedModel->plus($planChangeBD);

            $realityValues[$i] = round($accumulatedReality->toFloat(), 2);
            $modelValues[$i] = round($accumulatedModel->toFloat(), 2);

            // Ak sme v budúcnosti, realitu už neukazujeme (len trend plánu)
            if ($i > now()->month) {
                $realityValues[$i] = null;
            }
        }

        // Calculate is_ahead based on the LATEST available month
        $currentMonth = now()->month;
        $isAhead = ($realityValues[$currentMonth] ?? 0) >= ($modelValues[$currentMonth] ?? 0);

        return [
            'labels' => $labels,
            'reality_values' => array_values($realityValues),
            'model_values' => array_values($modelValues),
            'is_ahead' => $isAhead,
        ];
    }
}
