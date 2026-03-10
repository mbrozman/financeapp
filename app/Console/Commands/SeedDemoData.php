<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Account;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Transaction;
use App\Models\Investment;
use App\Models\InvestmentCategory;
use App\Models\InvestmentTransaction;
use App\Models\InvestmentPriceHistory;
use App\Models\PortfolioSnapshot;
use App\Models\Budget;
use App\Models\BudgetDefinition;
use App\Models\Goal;
use App\Models\RecurringTransaction;
use App\Models\FinancialPlan;
use App\Models\FinancialPlanItem;
use App\Enums\TransactionType;
use App\Enums\AssetType;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SeedDemoData extends Command
{
    protected $signature = 'app:seed-demo-data';
    protected $description = 'Populates the application with realistic demo data for testing.';

    public function handle()
    {
        $userId = 1;
        $this->info("Starting demo data seeding for User ID {$userId}...");

        // 1. CLEAR DATA
        $this->info("Clearing existing data...");
        DB::statement('SET CONSTRAINTS ALL DEFERRED');
        Transaction::whereHas('account', fn($q) => $q->where('user_id', $userId))->delete();
        InvestmentTransaction::whereHas('investment', fn($q) => $q->where('user_id', $userId))->delete();
        InvestmentPriceHistory::whereHas('investment', fn($q) => $q->where('user_id', $userId))->delete();
        Investment::where('user_id', $userId)->delete();
        InvestmentCategory::where('user_id', $userId)->delete();
        Budget::where('user_id', $userId)->delete();
        BudgetDefinition::where('user_id', $userId)->delete();
        Goal::where('user_id', $userId)->delete();
        RecurringTransaction::where('user_id', $userId)->delete();
        Category::where('user_id', $userId)->delete();
        Account::where('user_id', $userId)->delete();
        PortfolioSnapshot::where('user_id', $userId)->delete();
        
        // Financial Plans & Items (Special handling as they might not have user_id directly if not set up that way)
        FinancialPlanItem::whereHas('financialPlan', fn($q) => $q->where('user_id', $userId))->delete();
        FinancialPlan::where('user_id', $userId)->delete();

        // 2. FOUNDATION: Currencies & Accounts
        $this->info("Seeding foundation (Accounts & Categories)...");
        $eur = Currency::where('code', 'EUR')->first();
        $usd = Currency::where('code', 'USD')->first();

        $bankAccount = Account::create([
            'user_id' => $userId,
            'name' => 'Tatra Banka (Bežný účet)',
            'type' => 'personal',
            'currency_id' => $eur->id,
            'balance' => 0,
            'is_active' => true,
        ]);

        $tradingAccount = Account::create([
            'user_id' => $userId,
            'name' => 'Trading212 (Investičný)',
            'type' => 'investment',
            'currency_id' => $eur->id,
            'balance' => 0,
            'is_active' => true,
        ]);

        // 3. FINANCIAL PLAN & CATEGORIES
        $plan = FinancialPlan::create([
            'user_id' => $userId,
            'name' => 'Môj Finančný Plán',
            'monthly_income' => 2500,
            'expected_annual_return' => 8.00,
            'is_active' => true,
        ]);

        $itemLiving = FinancialPlanItem::create([
            'financial_plan_id' => $plan->id,
            'name' => 'Bývanie a strava',
            'percentage' => 50,
            'contributes_to_net_worth' => false,
            'applies_expected_return' => false,
        ]);

        $itemFun = FinancialPlanItem::create([
            'financial_plan_id' => $plan->id,
            'name' => 'Zábava a hobby',
            'percentage' => 30,
            'contributes_to_net_worth' => false,
            'applies_expected_return' => false,
        ]);

        $itemInvest = FinancialPlanItem::create([
            'financial_plan_id' => $plan->id,
            'name' => 'Investovanie a rezerva',
            'percentage' => 20,
            'contributes_to_net_worth' => true,
            'applies_expected_return' => true,
        ]);

        $catSalary = Category::create(['user_id' => $userId, 'name' => 'Výplata', 'type' => 'income', 'icon' => 'heroicon-o-briefcase', 'color' => '#10b981', 'is_active' => true]);
        $catRent = Category::create(['user_id' => $userId, 'name' => 'Nájomné', 'type' => 'expense', 'icon' => 'heroicon-o-home', 'color' => '#ef4444', 'financial_plan_item_id' => $itemLiving->id, 'is_active' => true]);
        $catFood = Category::create(['user_id' => $userId, 'name' => 'Potraviny', 'type' => 'expense', 'icon' => 'heroicon-o-shopping-cart', 'color' => '#f59e0b', 'financial_plan_item_id' => $itemLiving->id, 'is_active' => true]);
        $catNetflix = Category::create(['user_id' => $userId, 'name' => 'Zábava (Netflix)', 'type' => 'expense', 'icon' => 'heroicon-o-play', 'color' => '#8b5cf6', 'financial_plan_item_id' => $itemFun->id, 'is_active' => true]);
        $catInvesting = Category::create(['user_id' => $userId, 'name' => 'Vklad do investícií', 'type' => 'expense', 'icon' => 'heroicon-o-arrow-trending-up', 'color' => '#3b82f6', 'financial_plan_item_id' => $itemInvest->id, 'is_active' => true]);

        // 4. TRANSACTIONS (since Jan 1, 2026)
        $this->info("Seeding transactions...");
        $currentDate = Carbon::create(2026, 1, 1);
        $endDate = now();

        while ($currentDate->lte($endDate)) {
            // Salary on 10th
            if ($currentDate->day === 10) {
                Transaction::create([
                    'user_id' => $userId,
                    'account_id' => $bankAccount->id,
                    'category_id' => $catSalary->id,
                    'amount' => 2500,
                    'type' => 'income',
                    'transaction_date' => $currentDate->copy(),
                    'description' => 'Výplata od zamestnávateľa',
                ]);
                
                // Transfer to Trading212
                Transaction::create([
                    'user_id' => $userId,
                    'account_id' => $bankAccount->id,
                    'category_id' => $catInvesting->id,
                    'amount' => 500,
                    'type' => 'expense',
                    'transaction_date' => $currentDate->copy(),
                    'description' => 'Mesačný vklad na investície',
                ]);
                
                $tradingAccount->increment('balance', 500); // Manual fix because Transaction event mostly works but just to be sure 
            }

            // Rent on 1st
            if ($currentDate->day === 1) {
                Transaction::create([
                    'user_id' => $userId,
                    'account_id' => $bankAccount->id,
                    'category_id' => $catRent->id,
                    'amount' => 800,
                    'type' => 'expense',
                    'transaction_date' => $currentDate->copy(),
                    'description' => 'Nájomné za byt',
                ]);
            }

            // Food every 3 days
            if ($currentDate->day % 3 === 0) {
                Transaction::create([
                    'user_id' => $userId,
                    'account_id' => $bankAccount->id,
                    'category_id' => $catFood->id,
                    'amount' => rand(30, 80),
                    'type' => 'expense',
                    'transaction_date' => $currentDate->copy(),
                    'description' => 'Nákup potravín',
                ]);
            }

            $currentDate->addDay();
        }

        // 5. INVESTMENTS & BENCHMARKS
        $this->info("Seeding investments & benchmark data...");
        $this->call('app:init-benchmarks'); // Reuse the existing command for SPY/QQQ

        $invCatEtf = InvestmentCategory::updateOrCreate(['user_id' => $userId, 'name' => 'ETF'], ['slug' => 'etf', 'color' => '#3b82f6', 'is_active' => true]);
        
        $vwce = Investment::create([
            'user_id' => $userId,
            'account_id' => $tradingAccount->id,
            'investment_category_id' => $invCatEtf->id,
            'currency_id' => $eur->id,
            'ticker' => 'VWCE.DE',
            'name' => 'Vanguard FTSE All-World',
            'broker' => 'Trading212',
            'current_price' => 125.40,
            'asset_type' => AssetType::ETF,
        ]);

        // Simulated buys for VWCE
        InvestmentTransaction::create([
            'user_id' => $userId,
            'investment_id' => $vwce->id,
            'type' => TransactionType::BUY,
            'quantity' => 10,
            'price_per_unit' => 110.00,
            'commission' => 0,
            'currency_id' => $eur->id,
            'exchange_rate' => 1,
            'transaction_date' => Carbon::create(2026, 1, 15),
        ]);

        InvestmentTransaction::create([
            'user_id' => $userId,
            'investment_id' => $vwce->id,
            'type' => TransactionType::BUY,
            'quantity' => 5,
            'price_per_unit' => 118.00,
            'commission' => 0,
            'currency_id' => $eur->id,
            'exchange_rate' => 1,
            'transaction_date' => Carbon::create(2026, 2, 15),
        ]);

        // 6. SNAPSHOTS (Backfill)
        $this->info("Seeding portfolio snapshots...");
        $this->call('app:backfill-net-worth', ['start_amount' => 15000, 'months' => 3]);

        // 7. BUDGETS (2026-03)
        $this->info("Seeding budgets...");
        Budget::create([
            'user_id' => $userId, 
            'category_id' => $catFood->id, 
            'financial_plan_item_id' => $itemLiving->id,
            'limit_amount' => 500, 
            'valid_from' => '2026-01-01'
        ]);
        
        Budget::create([
            'user_id' => $userId, 
            'category_id' => $catRent->id, 
            'financial_plan_item_id' => $itemLiving->id,
            'limit_amount' => 800, 
            'valid_from' => '2026-01-01'
        ]);

        BudgetDefinition::create(['user_id' => $userId, 'category_id' => $catFood->id, 'financial_plan_item_id' => $itemLiving->id, 'amount' => 500, 'valid_from' => '2026-01-01']);
        BudgetDefinition::create(['user_id' => $userId, 'category_id' => $catRent->id, 'financial_plan_item_id' => $itemLiving->id, 'amount' => 800, 'valid_from' => '2026-01-01']);

        // 8. GOALS
        $this->info("Seeding goals...");
        Goal::create([
            'user_id' => $userId,
            'name' => 'Finančná rezerva',
            'target_amount' => 10000,
            'current_amount' => 4500,
            'deadline' => now()->addYear(),
            'color' => '#10b981',
        ]);

        Goal::create([
            'user_id' => $userId,
            'name' => 'Dovolenka Thajsko',
            'target_amount' => 3000,
            'current_amount' => 1200,
            'deadline' => now()->addMonths(6),
            'color' => '#f59e0b',
        ]);

        // 9. RECURRING TRANSACTIONS
        $this->info("Seeding recurring transactions...");
        RecurringTransaction::create([
            'user_id' => $userId,
            'account_id' => $bankAccount->id,
            'category_id' => $catSalary->id,
            'name' => 'Mesačná výplata',
            'amount' => 2500,
            'type' => 'income',
            'interval' => 'monthly',
            'next_date' => now()->addMonth()->setDay(10),
            'is_active' => true,
        ]);

        RecurringTransaction::create([
            'user_id' => $userId,
            'account_id' => $bankAccount->id,
            'category_id' => $catNetflix->id,
            'name' => 'Netflix Premium',
            'amount' => 15.99,
            'type' => 'expense',
            'interval' => 'monthly',
            'next_date' => now()->addMonth()->setDay(1),
            'is_active' => true,
        ]);

        $this->info("Demo data seeding completed successfully!");
    }
}

