<?php

namespace Database\Factories;

use App\Models\ActionCompletion;
use App\Models\ConservationAction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ActionCompletion> */
class ActionCompletionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::inRandomOrder()->first()?->id ?? User::factory(),
            'conservation_action_id' => ConservationAction::inRandomOrder()->first()?->id ?? ConservationAction::factory(),
            'completed_at' => fake()->dateTimeBetween('-30 days', 'now'),
        ];
    }
}
