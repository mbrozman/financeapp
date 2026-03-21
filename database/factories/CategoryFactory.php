<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->word(),
            'type' => $this->faker->randomElement(['expense', 'income']),
            'icon' => 'heroicon-o-tag',
            'color' => '#3b82f6',
        ];
    }
}
