<?php

namespace App\Services;

use App\Models\User;
use App\Models\Currency;
use App\Models\FinancialPlan;
use App\Models\FinancialPlanItem;
use App\Models\Category;
use App\Models\Account;
use App\Models\MonthlyIncome;

class UserInitializationService
{
    /**
     * Inicializuje základné dáta pre nového užívateľa.
     */
    public function initialize(User $user): void
    {
        // 1. Zabezpečíme meny (Globálne)
        $eur = Currency::firstOrCreate(['code' => 'EUR'], [
            'name' => 'Euro',
            'symbol' => '€',
            'exchange_rate' => 1.0,
        ]);

        Currency::firstOrCreate(['code' => 'USD'], [
            'name' => 'US Dollar',
            'symbol' => '$',
            'exchange_rate' => 1.08,
        ]);

        // 2. Ak už užívateľ má kategórie, nebudeme nič meniť
        if (Category::where('user_id', $user->id)->exists()) {
            return;
        }

        // 3. Finančný plán
        $plan = FinancialPlan::updateOrCreate(
            ['user_id' => $user->id],
            [
                'monthly_income' => 2200,
                'expected_annual_return' => 7.0,
                'is_active' => true,
            ]
        );

        // 4. Piliere (Šuflíky)
        $piliere = [
            ['name' => '1. HLAVNÉ VÝDAVKY', 'percentage' => 50, 'color' => '#ef4444', 'contributes_to_net_worth' => false],
            ['name' => '2. INVESTOVANIE', 'percentage' => 25, 'color' => '#3b82f6', 'contributes_to_net_worth' => true],
            ['name' => '3. REZERVA', 'percentage' => 15, 'color' => '#eab308', 'contributes_to_net_worth' => true],
            ['name' => '4. VRECKOVÉ', 'percentage' => 10, 'color' => '#22c55e', 'contributes_to_net_worth' => false],
        ];

        $pillarModels = [];
        foreach ($piliere as $p) {
            $pillarModels[$p['name']] = FinancialPlanItem::updateOrCreate(
                ['financial_plan_id' => $plan->id, 'name' => $p['name']],
                [
                    'percentage' => $p['percentage'],
                    'contributes_to_net_worth' => $p['contributes_to_net_worth'],
                ]
            );
        }

        // 5. Kategórie - PRÍJEM
        $catIncome = Category::create([
            'user_id' => $user->id,
            'name' => 'Príjem',
            'parent_id' => null,
            'type' => 'income',
            'icon' => 'heroicon-o-banknotes',
            'color' => '#10b981'
        ]);

        foreach (['Výplata', 'Prídavky', 'Odmena', 'Ostatný príjem'] as $subName) {
            Category::create([
                'user_id' => $user->id,
                'name' => $subName,
                'parent_id' => $catIncome->id,
                'type' => 'income'
            ]);
        }

        // 6. Kategórie - VÝDAVKY
        
        // --- PILIER 1 ---
        $p1 = $pillarModels['1. HLAVNÉ VÝDAVKY'];
        $categoriesP1 = [
            'Bývanie a domácnosť' => ['Nájomné', 'Hypotéka'],
            'Energie' => ['Elektrina', 'Plyn', 'Voda'],
            'Služby' => ['4ka', 'Internet', 'Vedenie účtu'],
            'Základné potreby' => ['Potraviny', 'Drogéria', 'Domáce potreby'],
            'Preprava' => ['Palivo', 'Verejná doprava'],
        ];

        foreach ($categoriesP1 as $parentName => $subs) {
            $parent = Category::create([
                'user_id' => $user->id,
                'name' => $parentName,
                'parent_id' => null,
                'type' => 'expense',
                'financial_plan_item_id' => $p1->id,
                'color' => '#ef4444',
                'icon' => 'heroicon-o-home'
            ]);
            foreach ($subs as $subName) {
                Category::create([
                    'user_id' => $user->id,
                    'name' => $subName,
                    'parent_id' => $parent->id,
                    'type' => 'expense'
                ]);
            }
        }

        // --- PILIER 2 ---
        $p2 = $pillarModels['2. INVESTOVANIE'];
        $parentInv = Category::create([
            'user_id' => $user->id,
            'name' => 'Investovanie',
            'parent_id' => null,
            'type' => 'expense',
            'financial_plan_item_id' => $p2->id,
            'color' => '#3b82f6',
            'icon' => 'heroicon-o-chart-bar'
        ]);
        foreach (['XTB', 'IBKR', 'XTB Betka', 'Partners Investments'] as $subName) {
            Category::create([
                'user_id' => $user->id,
                'name' => $subName,
                'parent_id' => $parentInv->id,
                'type' => 'expense'
            ]);
        }

        // --- PILIER 3 ---
        $p3 = $pillarModels['3. REZERVA'];
        $categoriesP3 = [
            'Železná rezerva' => ['Hotovosť', 'Sporenie'],
            'Sinking Funds (Amortizácia)' => ['Poistenie auta', 'Poistenie bytu', 'Daň', 'Daň za byt', 'Smeti', 'Ostatné poplatky', 'STK'],
        ];

        foreach ($categoriesP3 as $parentName => $subs) {
            $parent = Category::create([
                'user_id' => $user->id,
                'name' => $parentName,
                'parent_id' => null,
                'type' => 'expense',
                'financial_plan_item_id' => $p3->id,
                'color' => '#eab308',
                'icon' => 'heroicon-o-shield-check'
            ]);
            foreach ($subs as $subName) {
                Category::create([
                    'user_id' => $user->id,
                    'name' => $subName,
                    'parent_id' => $parent->id,
                    'type' => 'expense'
                ]);
            }
        }

        // --- PILIER 4 ---
        $p4 = $pillarModels['4. VRECKOVÉ'];
        $categoriesP4 = [
            'Life-style' => ['Reštaurácie', 'Bary', 'Kaviarne', 'Donášky', 'Dovolenka'],
            'Zábava' => ['Netflix', 'Kino', 'Google AI Pro'],
            'Nákupy' => ['Oblečenie', 'Elektronika'],
            'Ostatné' => ['Dary pre blízkych'],
        ];

        foreach ($categoriesP4 as $parentName => $subs) {
            $parent = Category::create([
                'user_id' => $user->id,
                'name' => $parentName,
                'parent_id' => null,
                'type' => 'expense',
                'financial_plan_item_id' => $p4->id,
                'color' => '#22c55e',
                'icon' => 'heroicon-o-gift'
            ]);
            foreach ($subs as $subName) {
                Category::create([
                    'user_id' => $user->id,
                    'name' => $subName,
                    'parent_id' => $parent->id,
                    'type' => 'expense'
                ]);
            }
        }

        // 7. Bankové Účty
        $banks = [
            ['name' => 'Prima banka', 'type' => 'bank'],
            ['name' => '365.bank', 'type' => 'bank'],
            ['name' => 'Revolut', 'type' => 'bank'],
            ['name' => 'XTB Broker', 'type' => 'investment'],
            ['name' => 'IBKR Broker', 'type' => 'investment'],
            ['name' => 'Partners Investments', 'type' => 'investment'],
        ];

        foreach ($banks as $b) {
            Account::updateOrCreate(
                ['user_id' => $user->id, 'name' => $b['name']],
                [
                    'type' => $b['type'],
                    'currency_id' => $eur->id,
                    'balance' => 0,
                    'is_active' => true,
                ]
            );
        }

        // 8. Mesačný príjem (MonthlyIncome record)
        MonthlyIncome::create([
            'user_id' => $user->id,
            'period' => now()->format('Y-m'),
            'amount' => 2200,
            'note' => 'Predvolený príjem'
        ]);
    }
}
