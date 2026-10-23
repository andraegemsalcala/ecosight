<?php

namespace Database\Seeders;

use App\Models\Animal;
use App\Models\ConservationAction;
use Illuminate\Database\Seeder;

/**
 * Guarantees the three household-pet animals have three responsible-pet-care
 * actions each. Idempotent: actions are matched on (animal_id, title).
 */
class ConservationActionPetSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->actions() as $label => $actions) {
            $animal = Animal::where('coco_label', $label)->first();
            if (! $animal) {
                continue;
            }
            // Rename the pre-fix title in place so re-seeding does not create
            // a duplicate action (idempotent cleanup).
            ConservationAction::where('animal_id', $animal->id)
                ->where('title', 'Houserabbit-proof your home')
                ->update(['title' => 'House-rabbit-proof your home']);
            foreach ($actions as $action) {
                ConservationAction::updateOrCreate(
                    ['animal_id' => $animal->id, 'title' => $action['title']],
                    array_diff_key($action, ['title' => null]),
                );
            }
        }
    }

    private function actions(): array
    {
        return [
            'pet_guinea_pig' => [
                ['title' => 'Provide a large, clean enclosure', 'description' => 'Give guinea pigs a roomy enclosure with fresh bedding, hiding spots, and daily cleaning — they need far more space than a small cage.', 'difficulty' => 'medium', 'category' => 'responsible pet care'],
                ['title' => 'Feed a high-quality hay-based diet', 'description' => 'Provide unlimited grass hay and fresh water daily, with limited pellet and vegetable supplements. Guinea pigs also need a vitamin C source every day.', 'difficulty' => 'easy', 'category' => 'responsible pet care'],
                ['title' => 'Never release pets into the wild', 'description' => 'Unwanted guinea pigs should be rehomed responsibly, never released — pets cannot survive or escape harm in wild habitats.', 'difficulty' => 'easy', 'category' => 'responsible pet care'],
            ],
            'pet_hamster' => [
                ['title' => 'Provide a wheel and deep bedding', 'description' => 'Hamsters are active at night and need a wheel plus deep bedding to dig a natural burrow, which supports their wellbeing.', 'difficulty' => 'easy', 'category' => 'responsible pet care'],
                ['title' => 'Keep the temperature stable', 'description' => 'Wild hamsters are sensitive to heat; keep the enclosure away from drafts, radiators, and direct sun.', 'difficulty' => 'easy', 'category' => 'responsible pet care'],
                ['title' => 'Adopt rather than buy when possible', 'description' => 'Rehoming an unwanted hamster reduces demand for breeding and gives an existing pet a second home.', 'difficulty' => 'medium', 'category' => 'responsible pet care'],
            ],
            'pet_rabbit' => [
                ['title' => 'House-rabbit-proof your home', 'description' => 'Protect cables and furniture and provide chew toys so indoor rabbits are safe and stimulated.', 'difficulty' => 'medium', 'category' => 'responsible pet care'],
                ['title' => 'Provide daily hay, water, and space', 'description' => 'Rabbits need unlimited grass hay, constant fresh water, and room to hop and sprint; a hutch alone is not enough.', 'difficulty' => 'easy', 'category' => 'responsible pet care'],
                ['title' => 'Spay or neuter to prevent strays', 'description' => 'Desexing prevents unwanted litters and reduces the number of rabbits abandoned outside, where they threaten wild habitats.', 'difficulty' => 'medium', 'category' => 'responsible pet care'],
            ],
        ];
    }
}
