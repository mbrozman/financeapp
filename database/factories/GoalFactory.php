<?php

namespace Database\Factories;

use App\Models\Goal;
use App\Models\User;
use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

class GoalFactory extends Factory
{
    protected $model = Goal::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->sentence(3),
            'target_amount' => $this->faker->randomFloat(2, 1000, 10000),
            'current_amount' => $this->faker->randomFloat(2, 0, 1000),
            'deadline' => $this->faker->dateTimeBetween('now', '+2 years'),
            'type' => $this->faker->randomElement(['saving', 'debt']),
            'color' => '#3b82f6',
        ];
    }
}
