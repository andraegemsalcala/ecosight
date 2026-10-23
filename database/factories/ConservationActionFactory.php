<?php

namespace Database\Factories;

use App\Models\Animal;
use App\Models\ConservationAction;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ConservationAction> */
class ConservationActionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'animal_id' => Animal::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'difficulty' => fake()->randomElement(['easy', 'medium', 'hard']),
            'category' => fake()->randomElement(['tree planting', 'waste reduction', 'habitat protection', 'community']),
        ];
    }
}
