<?php

namespace Database\Factories;

use App\Models\FinancialPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class FinancialPlanFactory extends Factory
{
    protected $model = FinancialPlan::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'monthly_income' => $this->faker->randomFloat(2, 2000, 5000),
            'expected_annual_return' => $this->faker->randomFloat(2, 5, 12),
            'is_active' => true,
        ];
    }
}
