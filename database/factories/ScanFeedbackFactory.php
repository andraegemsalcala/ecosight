<?php

namespace Database\Factories;

use App\Models\Scan;
use App\Models\ScanFeedback;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ScanFeedback> */
class ScanFeedbackFactory extends Factory
{
    public function definition(): array
    {
        $scan = Scan::inRandomOrder()->first() ?? Scan::factory()->create();

        return [
            'scan_id' => $scan->id,
            'user_id' => $scan->user_id,
            'is_correct' => fake()->boolean(85),
            'actual_label' => fake()->optional(0.4)->randomElement(['bird', 'cat', 'dog', 'horse', 'sheep', 'cow', 'elephant', 'bear', 'zebra', 'giraffe']),
        ];
    }
}
