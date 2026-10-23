<?php

namespace Database\Seeders;

use App\Models\ActionCompletion;
use App\Models\Animal;
use App\Models\ConservationAction;
use App\Models\User;
use Illuminate\Database\Seeder;

class ActionCompletionSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();
        $demo = User::where('email', 'demo@ecosight.test')->first();

        foreach ($users as $user) {
            $actions = ConservationAction::query()
                ->get();

            if ($demo && $user->is($demo)) {
                $chosen = Animal::all()
                    ->map(fn (Animal $animal) => $actions->firstWhere('animal_id', $animal->id))
                    ->filter();
            } else {
                $chosen = collect(range(1, fake()->numberBetween(0, 3)))
                    ->map(fn () => $actions->random());
            }

            foreach ($chosen as $action) {
                if (! $action) {
                    continue;
                }

                ActionCompletion::firstOrCreate(
                    ['user_id' => $user->id, 'conservation_action_id' => $action->id],
                    ['completed_at' => fake()->dateTimeBetween('-30 days', 'now')]
                );
            }
        }
    }
}
