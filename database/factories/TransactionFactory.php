<?php

namespace Database\Factories;

use App\Models\Transaction;
use App\Models\User;
use App\Models\Account;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'account_id' => Account::factory(),
            'category_id' => Category::factory(),
            'amount' => $this->faker->randomFloat(2, 1, 100),
            'transaction_date' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'description' => $this->faker->sentence(),
            'type' => $this->faker->randomElement(['expense', 'income']),
        ];
    }
}
