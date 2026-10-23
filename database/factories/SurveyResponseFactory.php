<?php

namespace Database\Factories;

use App\Models\SurveyResponse;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SurveyResponse> */
class SurveyResponseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::inRandomOrder()->first()?->id ?? User::factory(),
            'ease_rating' => fake()->numberBetween(1, 5),
            'speed_rating' => fake()->numberBetween(1, 5),
            'clarity_rating' => fake()->numberBetween(1, 5),
            'usefulness_rating' => fake()->numberBetween(1, 5),
            'comment' => fake()->optional(0.3)->sentence(),
        ];
    }
}
