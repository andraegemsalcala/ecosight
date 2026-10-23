<?php

namespace Database\Seeders;

use App\Models\ScannerEvent;
use App\Models\User;
use Illuminate\Database\Seeder;

class ScannerEventSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();

        foreach ($users as $user) {
            $events = fake()->numberBetween(4, 14);

            for ($i = 0; $i < $events; $i++) {
                ScannerEvent::create([
                    'user_id' => $user->id,
                    'type' => fake()->randomElement(['model-loaded', 'camera-started', 'detection-failed', 'low-confidence']),
                    'reason' => fake()->optional(0.6)->randomElement(['no-animal-detected', 'insecure-context', 'model-load-failed', 'camera-error']),
                    'response_time_ms' => fake()->optional(0.5)->numberBetween(80, 900),
                    'model_load_ms' => fake()->optional(0.3)->numberBetween(1500, 8000),
                    'camera_start_ms' => fake()->optional(0.3)->numberBetween(300, 4000),
                    'created_at' => fake()->dateTimeBetween('-30 days', 'now'),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
