<?php

namespace Database\Seeders;

use App\Models\Animal;
use Illuminate\Database\Seeder;

/**
 * Adds the three household-pet animals detected by MobileNet: guinea pig,
 * hamster, and domestic rabbit. Idempotent (updateOrCreate on coco_label).
 *
 * `model_labels` stores the EXACT ImageNet-1000 class strings (verified
 * against @tensorflow-models/mobilenet/src/imagenet_classes.ts). ImageNet
 * labels the domestic rabbit only as "Angora, Angora rabbit"; it has no plain
 * "rabbit" class, and wild "hare" / "cottontail" entries are deliberately not
 * mapped to a pet record.
 */
class AnimalPetSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->animals() as $label => $animal) {
            Animal::updateOrCreate(
                ['coco_label' => $label],
                [
                    'common_name' => $animal['common_name'],
                    'scientific_name' => $animal['scientific_name'],
                    'model_source' => 'mobilenet',
                    'model_labels' => $animal['model_labels'],
                    'clip_names' => $animal['clip_names'],
                    'is_scannable' => true,
                    'description' => $animal['description'],
                    'habitat' => $animal['habitat'],
                    'climate_impact' => $animal['climate_impact'],
                    'conservation_status' => $animal['conservation_status'],
                ]
            );
        }
    }

    /**
     * @return array<string, array{
     *     common_name: string,
     *     scientific_name: string,
     *     model_labels: string[],
     *     clip_names: string[],
     *     description: string,
     *     habitat: string,
     *     climate_impact: string,
     *     conservation_status: string,
     * }>
     */
    private function animals(): array
    {
        return [
            'pet_guinea_pig' => [
                'common_name' => 'Guinea pig',
                'scientific_name' => 'Cavia porcellus',
                'model_labels' => ['guinea pig, Cavia cobaya'],
                'clip_names' => ['a photo of a guinea pig', 'guinea pig', 'a pet guinea pig'],
                'description' => 'Guinea pigs are social, burrowing rodents that were domesticated from wild cavies in South America. They are herd animals that thrive with companions and need plenty of space and daily handling.',
                'habitat' => 'Domesticated; origin is the grassy plains and rocky scrub of the Andes in South America.',
                'climate_impact' => 'As indoor companions they are sensitive to overheating and drafts, so keeping them cool in warm weather matters.',
                'conservation_status' => 'Domesticated — check the IUCN Red List for wild cavy relatives',
            ],
            'pet_hamster' => [
                'common_name' => 'Hamster',
                'scientific_name' => 'Cricetinae',
                'model_labels' => ['hamster'],
                'clip_names' => ['a photo of a hamster', 'hamster', 'a pet hamster'],
                'description' => 'Hamsters are small, short-lived rodents that are nocturnal and territorial. Most pet hamsters descend from the Syrian hamster, which has lost much of its wild habitat in northern Syria and southern Turkey.',
                'habitat' => 'Domesticated; wild hamsters use dry steppe, desert edges, and burrows in parts of Eurasia.',
                'climate_impact' => 'Wild hamsters face habitat loss from farming expansion, while pet hamsters need stable indoor temperatures to stay healthy.',
                'conservation_status' => 'Domesticated — check the IUCN Red List for the wild Syrian hamster and steppe hamsters',
            ],
            'pet_rabbit' => [
                'common_name' => 'Domestic rabbit',
                'scientific_name' => 'Oryctolagus cuniculus',
                'model_labels' => ['Angora, Angora rabbit'],
                'clip_names' => ['a photo of a domestic rabbit', 'a pet rabbit', 'rabbit', 'an angora rabbit'],
                'description' => 'Domestic rabbits are social, crepuscular mammals kept as pets and fibre animals. The ImageNet model labels this class as the Angora rabbit, a wool breed; wild European rabbits, the same species, are separate conservation concern.',
                'habitat' => 'Domesticated; the wild European rabbit uses meadows, coastal dunes, and warren burrows across Western Europe and North Africa.',
                'climate_impact' => 'Wild rabbit populations decline under drought and habitat loss, while pet rabbits are heat-sensitive and need cool, shaded housing.',
                'conservation_status' => 'Domesticated — wild European rabbit is listed Endangered on the IUCN Red List',
            ],
        ];
    }
}
