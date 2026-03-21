<?php

namespace Database\Factories;

use App\Models\MonthlyIncome;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MonthlyIncomeFactory extends Factory
{
    protected $model = MonthlyIncome::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'amount' => $this->faker->randomFloat(2, 2000, 5000),
            'period' => now()->format('Y-m'),
            'note' => $this->faker->sentence(),
        ];
    }
}
