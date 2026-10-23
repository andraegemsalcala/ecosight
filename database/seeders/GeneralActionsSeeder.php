<?php

namespace Database\Seeders;

use App\Models\ConservationAction;
use Illuminate\Database\Seeder;

class GeneralActionsSeeder extends Seeder
{
    public function run(): void
    {
        $pool = config('ecosight.general_actions.pool', []);
        $byGroup = config('ecosight.general_actions.by_group', []);

        foreach ($byGroup as $group => $keys) {
            foreach ($keys as $key) {
                $action = $pool[$key] ?? null;

                if ($action === null) {
                    continue;
                }

                ConservationAction::updateOrCreate(
                    [
                        'animal_id' => null,
                        'group' => $group,
                        'title' => $action['title'],
                    ],
                    [
                        'description' => $action['description'],
                        'difficulty' => $action['difficulty'],
                        'category' => $action['category'],
                        'is_general' => true,
                    ],
                );
            }
        }
    }
}
