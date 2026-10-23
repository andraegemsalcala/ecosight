<?php

namespace Database\Seeders;

use App\Models\Animal;
use Illuminate\Database\Seeder;

/**
 * Adds the MobileNet-detected animal set to the library, on top of the ten
 * COCO-SSD records created by AnimalSeeder.
 *
 * `model_labels` stores the EXACT ImageNet-1000 class strings (verified
 * against @tensorflow-models/mobilenet/src/imagenet_classes.ts) that the
 * scanner maps to each record. Conservation statuses follow the IUCN Red List;
 * entries we cannot verify are flagged "Check IUCN Red List" and tracked in
 * docs/ANIMAL_DATA_TODO.md.
 */
class AnimalExpansionSeeder extends Seeder
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

        // Keep the ten COCO-SSD records marked as scannable with their metadata.
        foreach (Animal::where('model_source', 'coco-ssd')->get() as $animal) {
            $labels = $animal->model_labels ?: [$animal->coco_label];
            $animal->forceFill([
                'model_labels' => $labels,
                'is_scannable' => true,
            ])->save();
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
            'tiger' => [
                'common_name' => 'Tiger',
                'scientific_name' => 'Panthera tigris',
                'model_labels' => ['tiger, Panthera tigris'],
                'clip_names' => ['a photo of a tiger', 'tiger', 'a tiger in the wild'],
                'description' => 'Tigers are the largest cat species and sit at the top of their food webs. They depend on large connected forest habitats with healthy prey populations.',
                'habitat' => 'Tropical and temperate forests, grasslands, and mangrove swamps across Asia.',
                'climate_impact' => 'Rising temperatures and shifting rainfall can alter prey abundance and water sources, while fragmentation limits the large home ranges tigers need.',
                'conservation_status' => 'Endangered',
            ],
            'common_fox' => [
                'common_name' => 'Red fox',
                'scientific_name' => 'Vulpes vulpes',
                'model_labels' => ['red fox, Vulpes vulpes', 'kit fox, Vulpes macrotis'],
                'clip_names' => ['a photo of a red fox', 'red fox', 'a fox'],
                'description' => 'Red foxes are adaptable omnivores found across much of the Northern Hemisphere. They help control rodent populations and are among the most widespread wild canids.',
                'habitat' => 'Forests, grasslands, farmland, mountains, and urban edges.',
                'climate_impact' => 'Changing weather can shift prey availability and denning conditions, and milder winters may alter their range in some regions.',
                'conservation_status' => 'Least Concern',
            ],
            'cheetah' => [
                'common_name' => 'Cheetah',
                'scientific_name' => 'Acinonyx jubatus',
                'model_labels' => ['cheetah, chetah, Acinonyx jubatus'],
                'clip_names' => ['a photo of a cheetah', 'cheetah', 'a cheetah on the savanna'],
                'description' => 'Cheetahs are the fastest land animal, built for sprinting across open ground. Their survival depends on large protected landscapes with abundant prey.',
                'habitat' => 'Savannas, grasslands, and open woodland in parts of Africa, with a small Asian population.',
                'climate_impact' => 'Drought and changing rainfall reduce prey and water, and hotter conditions can increase energy demands on long chases.',
                'conservation_status' => 'Vulnerable',
            ],
            'brown_bear' => [
                'common_name' => 'Brown bear',
                'scientific_name' => 'Ursus arctos',
                'model_labels' => ['brown bear, bruin, Ursus arctos'],
                'clip_names' => ['a photo of a brown bear', 'brown bear', 'a grizzly bear'],
                'description' => 'Brown bears are large omnivores that shape ecosystems through feeding, seed dispersal, and soil disturbance. Populations range widely across the Northern Hemisphere.',
                'habitat' => 'Forests, mountains, tundra, and coastal areas across North America, Europe, and Asia.',
                'climate_impact' => 'Warmer temperatures alter food availability and denning seasons, and shrinking habitat can bring bears into closer contact with people.',
                'conservation_status' => 'Least Concern',
            ],
            'polar_bear' => [
                'common_name' => 'Polar bear',
                'scientific_name' => 'Ursus maritimus',
                'model_labels' => ['ice bear, polar bear, Ursus Maritimus, Thalarctos maritimus'],
                'clip_names' => ['a photo of a polar bear', 'polar bear', 'a polar bear on sea ice'],
                'description' => 'Polar bears are marine mammals that depend on sea ice to hunt seals. They are the largest living bear species.',
                'habitat' => 'Sea ice and Arctic coastal areas, following the seasonal extent of the ice pack.',
                'climate_impact' => 'Loss of summer sea ice shortens hunting seasons and forces bears to travel farther or fast for longer periods.',
                'conservation_status' => 'Vulnerable',
            ],
            'wolf' => [
                'common_name' => 'Grey wolf',
                'scientific_name' => 'Canis lupus',
                'model_labels' => ['timber wolf, grey wolf, gray wolf, Canis lupus'],
                'clip_names' => ['a photo of a grey wolf', 'grey wolf', 'gray wolf', 'a wolf in the wild'],
                'description' => 'Grey wolves are highly social pack hunters that can influence prey populations and ecosystem balance where they are protected.',
                'habitat' => 'Forests, tundra, mountains, and grasslands across North America, Europe, and Asia.',
                'climate_impact' => 'Changes in prey availability and habitat connectivity can shift wolf ranges and pack dynamics over time.',
                'conservation_status' => 'Least Concern',
            ],
            'red_panda' => [
                'common_name' => 'Red panda',
                'scientific_name' => 'Ailurus fulgens',
                'model_labels' => ['lesser panda, red panda, panda, bear cat, cat bear, Ailurus fulgens'],
                'clip_names' => ['a photo of a red panda', 'red panda', 'a lesser panda'],
                'description' => 'Red pandas are bamboo specialists that are only distantly related to giant pandas. They are sensitive to forest disturbance and temperature extremes.',
                'habitat' => 'Temperate bamboo forests in the eastern Himalayas and southwestern China.',
                'climate_impact' => 'Warming can push their preferred bamboo forest into narrower altitude bands, and extreme weather threatens den and food security.',
                'conservation_status' => 'Endangered',
            ],
            'penguin' => [
                'common_name' => 'King penguin',
                'scientific_name' => 'Aptenodytes patagonicus',
                'model_labels' => ['king penguin, Aptenodytes patagonica'],
                'clip_names' => ['a photo of a king penguin', 'penguin', 'a penguin colony'],
                'description' => 'King penguins are the second-largest penguin species and breed in large colonial rookeries on sub-Antarctic islands.',
                'habitat' => 'Sub-Antarctic islands and surrounding waters, commuting long distances to feed.',
                'climate_impact' => 'Shifts in ocean temperatures affect the fish and squid they rely on, while severe weather can disrupt colonies and breeding beaches.',
                'conservation_status' => 'Least Concern',
            ],
            'pelican' => [
                'common_name' => 'Pelican',
                'scientific_name' => 'Pelecanus spp.',
                'model_labels' => ['pelican'],
                'clip_names' => ['a photo of a pelican', 'pelican', 'a white pelican'],
                'description' => 'Pelicans are large water birds that feed by scooping fish with their distinctive pouch. They nest in colonies near lakes, coasts, and wetlands.',
                'habitat' => 'Coasts, estuaries, lakes, and wetlands on most continents.',
                'climate_impact' => 'Drought and wetland loss can reduce fish availability and nesting sites, while sea-level rise threatens coastal colonies.',
                'conservation_status' => 'Check IUCN Red List (Dalmatian pelican Near Threatened; most other species Least Concern)',
            ],
            'lion' => [
                'common_name' => 'Lion',
                'scientific_name' => 'Panthera leo',
                'model_labels' => ['lion, king of beasts, Panthera leo'],
                'clip_names' => ['a photo of a lion', 'lion', 'a male lion'],
                'description' => 'Lions are social big cats that live in prides and depend on large prey herds and open landscapes with space to roam.',
                'habitat' => 'Savannas, grasslands, and open woodland across parts of sub-Saharan Africa, with a small Asiatic population in India.',
                'climate_impact' => 'Drought and habitat change reduce prey numbers and water access, increasing pressure on the large territories prides require.',
                'conservation_status' => 'Vulnerable',
            ],
            'lynx' => [
                'common_name' => 'Lynx',
                'scientific_name' => 'Lynx lynx / Lynx rufus',
                'model_labels' => ['lynx, catamount'],
                'clip_names' => ['a photo of a lynx', 'lynx', 'a wild cat with tufted ears'],
                'description' => 'Lynx are medium-sized wild cats with tufted ears and large paws. The Eurasian lynx hunts deer and hares; the bobcat occupies dense cover in North America.',
                'habitat' => 'Boreal forests, mountains, and scrub habitats across Eurasia and North America.',
                'climate_impact' => 'Changing snow conditions and prey cycles can affect hunting success, while habitat fragmentation isolates small populations.',
                'conservation_status' => 'Least Concern',
            ],
            'wild_boar' => [
                'common_name' => 'Wild boar',
                'scientific_name' => 'Sus scrofa',
                'model_labels' => ['wild boar, boar, Sus scrofa'],
                'clip_names' => ['a photo of a wild boar', 'wild boar', 'a boar in the forest'],
                'description' => 'Wild boars are adaptable omnivores that root through soil for food. They reproduce quickly and occur across Europe, Asia, and North Africa.',
                'habitat' => 'Forests, scrublands, farmland edges, and wetlands.',
                'climate_impact' => 'Milder winters and changing landscapes can influence population growth, while drought affects water and mast availability.',
                'conservation_status' => 'Least Concern',
            ],
            'koala' => [
                'common_name' => 'Koala',
                'scientific_name' => 'Phascolarctos cinereus',
                'model_labels' => ['koala, koala bear, kangaroo bear, native bear, Phascolarctos cinereus'],
                'clip_names' => ['a photo of a koala', 'koala', 'a koala in a eucalyptus tree'],
                'description' => 'Koalas are marsupials that feed almost entirely on eucalyptus leaves. They rely on healthy, connected woodland for food and safe movement between trees.',
                'habitat' => 'Eucalyptus forests and woodlands in eastern and southeastern Australia.',
                'climate_impact' => 'Longer heatwaves, bushfires, and drought destroy the trees koalas depend on and reduce the water found in leaves.',
                'conservation_status' => 'Vulnerable',
            ],
            'kangaroo' => [
                'common_name' => 'Kangaroo',
                'scientific_name' => 'Macropodidae',
                'model_labels' => ['wallaby, brush kangaroo'],
                'clip_names' => ['a photo of a kangaroo', 'kangaroo', 'a wallaby'],
                'description' => 'Kangaroos and wallabies are Australia\'s large grazing marsupials. They move with powerful hopping and depend on open vegetation and seasonal water.',
                'habitat' => 'Grasslands, savannas, woodlands, and scrub across Australia.',
                'climate_impact' => 'Drought reduces grass growth and water availability, while hotter conditions increase heat stress for these large-bodied grazers.',
                'conservation_status' => 'Least Concern',
            ],
            'giant_panda' => [
                'common_name' => 'Giant panda',
                'scientific_name' => 'Ailuropoda melanoleuca',
                'model_labels' => ['giant panda, panda, panda bear, coon bear, Ailuropoda melanoleuca'],
                'clip_names' => ['a photo of a giant panda', 'giant panda', 'a panda eating bamboo'],
                'description' => 'Giant pandas are bears that specialise in eating bamboo, consuming large amounts daily. They depend on connected mountain forests.',
                'habitat' => 'Bamboo forests in the mountains of central China.',
                'climate_impact' => 'Warming can compress the bamboo belts pandas rely on, and fragmented corridors make it harder for them to move between feeding areas.',
                'conservation_status' => 'Vulnerable',
            ],
            'jaguar' => [
                'common_name' => 'Jaguar',
                'scientific_name' => 'Panthera onca',
                'model_labels' => ['jaguar, panther, Panthera onca, Felis onca'],
                'clip_names' => ['a photo of a jaguar', 'jaguar', 'a spotted big cat in the jungle'],
                'description' => 'Jaguars are the largest cats in the Americas and strong swimmers that often hunt near water. They need large forest territories with healthy prey.',
                'habitat' => 'Tropical rainforests, wetlands, and savannas from Mexico to South America.',
                'climate_impact' => 'River and wetland changes, drought, and forest loss can reduce prey and break up the corridors jaguars use to move between habitats.',
                'conservation_status' => 'Near Threatened',
            ],
            'gorilla' => [
                'common_name' => 'Western gorilla',
                'scientific_name' => 'Gorilla gorilla',
                'model_labels' => ['gorilla, Gorilla gorilla'],
                'clip_names' => ['a photo of a gorilla', 'gorilla', 'a western gorilla'],
                'description' => 'Western gorillas are large great apes that live in family groups. They are threatened by habitat loss and disease and depend on intact forests.',
                'habitat' => 'Tropical lowland and swamp forests across western Central Africa.',
                'climate_impact' => 'Changing rainfall and forest conditions affect food plants, while warming may expand the reach of disease in some regions.',
                'conservation_status' => 'Critically Endangered',
            ],
            'otter' => [
                'common_name' => 'Eurasian otter',
                'scientific_name' => 'Lutra lutra',
                'model_labels' => ['otter'],
                'clip_names' => ['a photo of an otter', 'otter', 'a river otter swimming'],
                'description' => 'Eurasian otters are semi-aquatic predators found along rivers, lakes, and coasts. Clean water and healthy fish stocks are essential for their survival.',
                'habitat' => 'Rivers, lakes, wetlands, and coastal areas across Europe, Asia, and North Africa.',
                'climate_impact' => 'Drought lowers water levels and fish availability, while flooding can wash away the dens otters use near the water\'s edge.',
                'conservation_status' => 'Near Threatened',
            ],
            'leopard' => [
                'common_name' => 'Leopard',
                'scientific_name' => 'Panthera pardus',
                'model_labels' => ['leopard, Panthera pardus'],
                'clip_names' => ['a photo of a leopard', 'leopard', 'a spotted big cat in a tree'],
                'description' => 'Leopards are adaptable big cats found in forests, savannas, and mountains. They are powerful climbers and skilled ambush hunters.',
                'habitat' => 'Forests, savannas, woodlands, and mountainous areas across Africa and Asia.',
                'climate_impact' => 'Habitat loss and reduced prey from changing land and climate can push leopards closer to humans and increase conflict.',
                'conservation_status' => 'Vulnerable',
            ],
            'gazelle' => [
                'common_name' => 'Gazelle',
                'scientific_name' => 'Gazella spp.',
                'model_labels' => ['gazelle'],
                'clip_names' => ['a photo of a gazelle', 'gazelle', 'a small antelope on the plains'],
                'description' => 'Gazelles are swift, graceful antelopes of open country. Species differ greatly in how threatened they are, and some desert gazelles are endangered.',
                'habitat' => 'Grasslands, savannas, arid plains, and desert margins across Africa and the Middle East.',
                'climate_impact' => 'Drought and desertification shrink the grasses gazelles depend on, and fences and roads can block seasonal movement.',
                'conservation_status' => 'Check IUCN Red List (status varies widely by species)',
            ],
        ];
    }
}
