<?php

namespace Database\Seeders;

use App\Models\Scan;
use App\Models\ScanFeedback;
use Illuminate\Database\Seeder;

class ScanFeedbackSeeder extends Seeder
{
    public function run(): void
    {
        $scans = Scan::query()
            ->inRandomOrder()
            ->take((int) ceil(Scan::count() * 0.5))
            ->get();

        foreach ($scans as $scan) {
            ScanFeedback::updateOrCreate(
                ['scan_id' => $scan->id],
                [
                    'user_id' => $scan->user_id,
                    'is_correct' => fake()->boolean(85),
                    'actual_label' => fake()->optional(0.4)->randomElement(['bird', 'cat', 'dog', 'horse', 'sheep', 'cow', 'elephant', 'bear', 'zebra', 'giraffe']),
                ]
            );
        }
    }
}
