<?php

namespace Database\Factories;

use App\Models\Animal;
use App\Models\Scan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Scan> */
class ScanFactory extends Factory
{
    public function definition(): array
    {
        $animal = Animal::inRandomOrder()->first() ?? Animal::factory()->create();

        return [
            'user_id' => User::inRandomOrder()->first()?->id ?? User::factory(),
            'animal_id' => $animal->id,
            'detected_label' => $animal->coco_label,
            'confidence' => fake()->randomFloat(4, 0.60, 0.99),
            'response_time_ms' => fake()->numberBetween(80, 900),
            'notes' => fake()->optional(0.25)->sentence(),
        ];
    }
}
