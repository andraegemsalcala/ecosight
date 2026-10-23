<?php

namespace Database\Seeders;

use App\Models\Animal;
use Illuminate\Database\Seeder;

class AnimalSeeder extends Seeder
{
    public function run(): void
    {
        $animals = [
            [
                'common_name' => 'Bird',
                'scientific_name' => 'Aves',
                'coco_label' => 'bird',
                'clip_names' => ['a photo of a bird', 'bird', 'a small flying bird'],
                'description' => 'Birds are a diverse group of vertebrates found in forests, wetlands, grasslands, farms, and cities. Different species fill important roles such as pollination, seed dispersal, and insect control.',
                'habitat' => 'Habitats range from forests and wetlands to grasslands, coastal areas, farms, and urban green spaces.',
                'climate_impact' => 'Changing temperature and rainfall patterns can shift food availability, breeding seasons, migration routes, and suitable habitat. Extreme weather can also damage nesting areas.',
                'conservation_status' => fake()->randomElement(['Varies by species', 'Least Concern for many common species']),
            ],
            [
                'common_name' => 'Cat',
                'scientific_name' => 'Felis catus',
                'coco_label' => 'cat',
                'clip_names' => ['a photo of a cat', 'cat', 'a domestic cat'],
                'description' => 'Domestic cats live closely with people, while related wild cats depend on natural habitats. Responsible pet ownership can reduce pressure on local wildlife.',
                'habitat' => 'Domestic cats commonly live in human settlements. Wild felids depend on forests, grasslands, mountains, and other natural habitats.',
                'climate_impact' => 'Climate change can affect prey availability and habitat conditions for wild felids. For outdoor domestic cats, heat and extreme weather can also increase health and safety risks.',
                'conservation_status' => fake()->randomElement(['Domestic species; wild felids vary by species', 'Varies among related wild felid species']),
            ],
            [
                'common_name' => 'Dog',
                'scientific_name' => 'Canis lupus familiaris',
                'coco_label' => 'dog',
                'clip_names' => ['a photo of a dog', 'dog', 'a domestic dog'],
                'description' => 'Dogs are domesticated mammals that live in close association with people. Responsible care includes managing waste and preventing uncontrolled impacts on wildlife.',
                'habitat' => 'Domestic dogs live mainly in human communities, homes, and managed outdoor spaces.',
                'climate_impact' => 'Hotter temperatures and extreme weather can increase heat stress and disease risks for dogs. Community planning can provide safer spaces during severe weather.',
                'conservation_status' => fake()->randomElement(['Domesticated species', 'Managed domestic species']),
            ],
            [
                'common_name' => 'Horse',
                'scientific_name' => 'Equus caballus',
                'coco_label' => 'horse',
                'clip_names' => ['a photo of a horse', 'horse', 'a horse in a field'],
                'description' => 'Horses are large grazing mammals that have been domesticated for thousands of years and are also part of managed agricultural landscapes.',
                'habitat' => 'Domestic horses are kept in pastures, farms, ranches, and managed facilities with access to food, water, and shelter.',
                'climate_impact' => 'Heat waves, drought, flooding, and changing pasture conditions can affect water, forage, shelter, and animal health.',
                'conservation_status' => fake()->randomElement(['Domesticated species', 'Managed domestic species']),
            ],
            [
                'common_name' => 'Sheep',
                'scientific_name' => 'Ovis aries',
                'coco_label' => 'sheep',
                'clip_names' => ['a photo of a sheep', 'sheep', 'a flock of sheep'],
                'description' => 'Sheep are domesticated grazing mammals commonly raised for wool, meat, and other agricultural products.',
                'habitat' => 'Managed grasslands, pasture, farms, and rangelands with suitable forage and water.',
                'climate_impact' => 'Drought, heat, flooding, and changing vegetation can reduce pasture quality and water availability, increasing pressure on farming systems.',
                'conservation_status' => fake()->randomElement(['Domesticated species', 'Managed domestic species']),
            ],
            [
                'common_name' => 'Cow',
                'scientific_name' => 'Bos taurus',
                'coco_label' => 'cow',
                'clip_names' => ['a photo of a cow', 'cow', 'cattle in a pasture'],
                'description' => 'Cattle are domesticated grazing mammals used in agriculture. Sustainable pasture and water management helps protect both livestock and surrounding land.',
                'habitat' => 'Pastures, grasslands, farms, and managed rangelands.',
                'climate_impact' => 'Heat stress, drought, floods, and reduced forage quality can affect cattle health and farm productivity. Better land and water management can improve resilience.',
                'conservation_status' => fake()->randomElement(['Domesticated species', 'Managed domestic species']),
            ],
            [
                'common_name' => 'Elephant',
                'scientific_name' => 'Elephantidae',
                'coco_label' => 'elephant',
                'clip_names' => ['a photo of an elephant', 'elephant', 'an african elephant', 'an asian elephant'],
                'description' => 'Elephants are large herbivores that shape ecosystems by moving vegetation, opening pathways, and dispersing seeds.',
                'habitat' => 'African elephants occupy savannas, forests, and other landscapes. Asian elephants depend heavily on forests and grasslands.',
                'climate_impact' => 'Drought and changing rainfall can reduce water and food availability. Habitat fragmentation can make it harder for elephants to move between resources.',
                'conservation_status' => 'Threat levels vary by elephant species',
            ],
            [
                'common_name' => 'Bear',
                'scientific_name' => 'Ursidae',
                'coco_label' => 'bear',
                'clip_names' => ['a photo of a bear', 'bear', 'a wild bear'],
                'description' => 'Bears are omnivorous mammals found in several parts of the world. They can influence ecosystems through feeding, seed dispersal, and interactions with other species.',
                'habitat' => 'Forests, mountains, tundra, grasslands, and other habitats depending on the species.',
                'climate_impact' => 'Warmer temperatures, reduced sea ice for polar bears, altered food timing, and changing forest conditions can affect different bear species.',
                'conservation_status' => 'Varies by species',
            ],
            [
                'common_name' => 'Zebra',
                'scientific_name' => 'Equus quagga',
                'coco_label' => 'zebra',
                'clip_names' => ['a photo of a zebra', 'zebra', 'zebras on the savanna'],
                'description' => 'Zebras are social grazing mammals native to Africa. Their grazing helps shape grassland ecosystems and they are part of complex food webs.',
                'habitat' => 'African grasslands, savannas, and open woodland areas.',
                'climate_impact' => 'Drought and changing rainfall can reduce grass and water availability. Fragmented landscapes can limit movement toward seasonal resources.',
                'conservation_status' => 'Varies by zebra species',
            ],
            [
                'common_name' => 'Giraffe',
                'scientific_name' => 'Giraffa camelopardalis',
                'coco_label' => 'giraffe',
                'clip_names' => ['a photo of a giraffe', 'giraffe', 'a giraffe on the savanna'],
                'description' => 'Giraffes are tall browsing mammals that feed mainly on leaves and help move nutrients through savanna ecosystems.',
                'habitat' => 'African savannas, grasslands, shrublands, and open woodland.',
                'climate_impact' => 'Changes in rainfall and vegetation can affect browse availability and water sources. Habitat fragmentation can also reduce access to food and safe movement routes.',
                'conservation_status' => 'Conservation status varies among giraffe populations',
            ],
        ];

        foreach ($animals as $animal) {
            Animal::updateOrCreate(['coco_label' => $animal['coco_label']], $animal);
        }
    }
}
