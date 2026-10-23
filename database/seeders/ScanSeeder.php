<?php

namespace Database\Seeders;

use App\Models\Animal;
use App\Models\Scan;
use App\Models\User;
use Illuminate\Database\Seeder;

class ScanSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();
        $animals = Animal::all();

        for ($i = 0; $i < 30; $i++) {
            $animal = $animals->random();

            Scan::create([
                'user_id' => $users->random()->id,
                'animal_id' => $animal->id,
                'detected_label' => $animal->coco_label,
                'confidence' => fake()->randomFloat(4, 0.60, 0.99),
                'response_time_ms' => fake()->numberBetween(80, 900),
                'notes' => fake()->optional(0.2)->sentence(),
                'created_at' => fake()->dateTimeBetween('-30 days', 'now'),
                'updated_at' => now(),
            ]);
        }
    }
}
