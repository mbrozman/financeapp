<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\InvestmentCategory;

class InvestmentCategoryFactory extends Factory
{
    protected $model = InvestmentCategory::class;

    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'name' => $this->faker->unique()->word(),
            'slug' => $this->faker->unique()->slug(),
            'color' => $this->faker->hexColor(),
            'is_active' => true,
        ];
    }
}
