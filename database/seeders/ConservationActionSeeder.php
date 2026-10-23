<?php

namespace Database\Seeders;

use App\Models\Animal;
use App\Models\ConservationAction;
use Illuminate\Database\Seeder;

class ConservationActionSeeder extends Seeder
{
    public function run(): void
    {
        $actions = [
            'bird' => [
                ['title' => 'Plant native flowering plants', 'description' => 'Add native plants that provide food and shelter for local birds and insects.', 'difficulty' => 'easy', 'category' => 'tree planting'],
                ['title' => 'Protect nesting spaces', 'description' => 'Avoid disturbing nests and keep cats and dogs from sensitive nesting areas.', 'difficulty' => 'easy', 'category' => 'habitat protection'],
            ],
            'cat' => [
                ['title' => 'Keep cats safely supervised outdoors', 'description' => 'Use supervised outdoor time or enclosed spaces to reduce pressure on local wildlife.', 'difficulty' => 'medium', 'category' => 'habitat protection'],
                ['title' => 'Support native wildlife gardens', 'description' => 'Maintain plant-rich spaces that support local insects and birds without introducing invasive species.', 'difficulty' => 'medium', 'category' => 'community'],
            ],
            'dog' => [
                ['title' => 'Dispose of pet waste properly', 'description' => 'Collect pet waste and place it in appropriate bins to help keep waterways and public spaces clean.', 'difficulty' => 'easy', 'category' => 'waste reduction'],
                ['title' => 'Use a leash near wildlife areas', 'description' => 'Keep dogs controlled around habitats where wildlife may be nesting, feeding, or resting.', 'difficulty' => 'easy', 'category' => 'habitat protection'],
            ],
            'horse' => [
                ['title' => 'Protect pasture vegetation', 'description' => 'Rotate grazing areas when possible to reduce soil damage and allow vegetation to recover.', 'difficulty' => 'medium', 'category' => 'habitat protection'],
                ['title' => 'Maintain reliable water access', 'description' => 'Keep clean water available during hot and dry periods and monitor animals for heat stress.', 'difficulty' => 'easy', 'category' => 'community'],
            ],
            'sheep' => [
                ['title' => 'Practice rotational grazing', 'description' => 'Move livestock between pasture areas to give vegetation and soil time to recover.', 'difficulty' => 'medium', 'category' => 'habitat protection'],
                ['title' => 'Reduce water waste on farms', 'description' => 'Repair leaks and use suitable watering systems to protect limited water resources.', 'difficulty' => 'easy', 'category' => 'waste reduction'],
            ],
            'cow' => [
                ['title' => 'Improve pasture cover', 'description' => 'Maintain healthy ground cover to reduce erosion and help soil retain moisture.', 'difficulty' => 'medium', 'category' => 'habitat protection'],
                ['title' => 'Plant shade trees', 'description' => 'Add suitable trees where appropriate to provide shade and improve farm resilience.', 'difficulty' => 'hard', 'category' => 'tree planting'],
            ],
            'elephant' => [
                ['title' => 'Support habitat corridors', 'description' => 'Support local projects that connect protected habitats and maintain wildlife movement routes.', 'difficulty' => 'hard', 'category' => 'habitat protection'],
                ['title' => 'Reduce waste near wildlife areas', 'description' => 'Keep food and plastic waste away from natural habitats and protected areas.', 'difficulty' => 'easy', 'category' => 'waste reduction'],
            ],
            'bear' => [
                ['title' => 'Secure food waste', 'description' => 'Store food and garbage securely in areas where bears may be present to reduce dangerous encounters.', 'difficulty' => 'easy', 'category' => 'waste reduction'],
                ['title' => 'Protect forest habitat', 'description' => 'Support responsible forest management and avoid damaging sensitive wildlife areas.', 'difficulty' => 'medium', 'category' => 'habitat protection'],
            ],
            'zebra' => [
                ['title' => 'Protect grassland areas', 'description' => 'Support land management that maintains native grasslands and seasonal water sources.', 'difficulty' => 'medium', 'category' => 'habitat protection'],
                ['title' => 'Support community conservation', 'description' => 'Share reliable conservation information and support local projects that protect wildlife habitat.', 'difficulty' => 'easy', 'category' => 'community'],
            ],
            'giraffe' => [
                ['title' => 'Protect native trees', 'description' => 'Support tree and woodland protection because browse plants are important food sources for giraffes.', 'difficulty' => 'medium', 'category' => 'tree planting'],
                ['title' => 'Reduce single-use waste', 'description' => 'Choose reusable products and dispose of plastic correctly so waste is less likely to reach wildlife habitats.', 'difficulty' => 'easy', 'category' => 'waste reduction'],
            ],
        ];

        foreach ($actions as $label => $animalActions) {
            $animal = Animal::where('coco_label', $label)->firstOrFail();

            foreach ($animalActions as $action) {
                ConservationAction::updateOrCreate(
                    ['animal_id' => $animal->id, 'title' => $action['title']],
                    array_diff_key($action, ['title' => null]),
                );
            }
        }
    }
}
