<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\InvestmentTransaction;
use App\Models\Investment;
use App\Models\Currency;
use App\Enums\TransactionType;

class InvestmentTransactionFactory extends Factory
{
    protected $model = InvestmentTransaction::class;

    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'investment_id' => Investment::factory(),
            'currency_id' => Currency::factory(),
            'type' => TransactionType::BUY,
            'quantity' => (string) $this->faker->randomFloat(4, 1, 100),
            'price_per_unit' => (string) $this->faker->randomFloat(2, 10, 500),
            'commission' => (string) $this->faker->randomFloat(2, 0, 10),
            'transaction_date' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'exchange_rate' => '1.0',
        ];
    }
}
