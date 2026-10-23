<?php

namespace Database\Factories;

use App\Models\ScannerEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ScannerEvent> */
class ScannerEventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::inRandomOrder()->first()?->id ?? User::factory(),
            'type' => fake()->randomElement(['model-loaded', 'camera-started', 'detection-failed', 'low-confidence']),
            'reason' => fake()->optional(0.6)->randomElement(['no-animal-detected', 'insecure-context', 'model-load-failed', 'camera-error']),
            'response_time_ms' => fake()->optional(0.5)->numberBetween(80, 900),
            'model_load_ms' => fake()->optional(0.3)->numberBetween(1500, 8000),
            'camera_start_ms' => fake()->optional(0.3)->numberBetween(300, 4000),
        ];
    }
}
