<?php

namespace Database\Seeders;

use App\Models\SurveyResponse;
use App\Models\User;
use Illuminate\Database\Seeder;

class SurveyResponseSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();

        foreach ($users as $user) {
            SurveyResponse::factory()->count(fake()->numberBetween(0, 2))->create([
                'user_id' => $user->id,
                'created_at' => fake()->dateTimeBetween('-30 days', 'now'),
                'updated_at' => now(),
            ]);
        }
    }
}
