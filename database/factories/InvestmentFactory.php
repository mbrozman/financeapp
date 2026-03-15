<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Investment;
use App\Models\User;
use App\Models\Account;
use App\Models\InvestmentCategory;
use App\Models\Currency;

class InvestmentFactory extends Factory
{
    protected $model = Investment::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'account_id' => Account::factory(),
            'investment_category_id' => InvestmentCategory::factory(),
            'currency_id' => Currency::factory(),
            'ticker' => strtoupper($this->faker->lexify('????')),
            'name' => $this->faker->company(),
            'broker' => $this->faker->company(),
            'current_price' => (string) $this->faker->randomFloat(2, 10, 500),
            'is_archived' => false,
        ];
    }
}
