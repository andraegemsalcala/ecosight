<?php

namespace Database\Seeders;

use App\Models\Animal;
use App\Models\ConservationAction;
use Illuminate\Database\Seeder;

/**
 * Guarantees every animal in the library has at least three conservation
 * actions. Idempotent: actions are matched on (animal_id, title), so repeated
 * runs never create duplicates.
 */
class ConservationActionExpansionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Animal::where('model_source', 'mobilenet')->get() as $animal) {
            foreach ($this->actions()[$animal->coco_label] ?? [] as $action) {
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
            'tiger' => [
                ['title' => 'Support habitat corridors', 'description' => 'Support projects that connect forest reserves so tigers can move safely between breeding areas.', 'difficulty' => 'hard', 'category' => 'habitat protection'],
                ['title' => 'Share reliable tiger conservation information', 'description' => 'Spread accurate information about tiger threats and avoid sharing harmful wildlife content.', 'difficulty' => 'easy', 'category' => 'community'],
                ['title' => 'Choose responsibly sourced products', 'description' => 'Buy timber and agricultural products that avoid driving forest loss in tiger range countries.', 'difficulty' => 'medium', 'category' => 'waste reduction'],
            ],
            'common_fox' => [
                ['title' => 'Leave wild spaces undisturbed', 'description' => 'Let dens and scrub cover remain so foxes and their young are not disturbed.', 'difficulty' => 'easy', 'category' => 'habitat protection'],
                ['title' => 'Keep your garden wildlife-friendly', 'description' => 'Maintain hedges and plant cover that give small wild mammals safe routes through neighbourhoods.', 'difficulty' => 'easy', 'category' => 'community'],
                ['title' => 'Dispose of food waste securely', 'description' => 'Secure bins so animals do not rely on human leftovers, which can fuel conflict.', 'difficulty' => 'easy', 'category' => 'waste reduction'],
            ],
            'cheetah' => [
                ['title' => 'Support large protected areas', 'description' => 'Support parks and ranches that keep open grassland habitat connected for cheetahs.', 'difficulty' => 'hard', 'category' => 'habitat protection'],
                ['title' => 'Learn about human-wildlife coexistence', 'description' => 'Learn how livestock guard animals and herding practices reduce conflict with carnivores.', 'difficulty' => 'medium', 'category' => 'community'],
                ['title' => 'Share verified conservation facts', 'description' => 'Promote accurate information about cheetah threats and conservation success stories.', 'difficulty' => 'easy', 'category' => 'community'],
            ],
            'brown_bear' => [
                ['title' => 'Secure food and waste in bear country', 'description' => 'Store food and garbage so bears are not attracted to human sites.', 'difficulty' => 'medium', 'category' => 'waste reduction'],
                ['title' => 'Follow trail and camping etiquette', 'description' => 'Keep clean camps and make noise in bear habitat to avoid surprising bears.', 'difficulty' => 'easy', 'category' => 'habitat protection'],
                ['title' => 'Support responsible forest management', 'description' => 'Support forestry that keeps interior forest and old-growth habitat intact.', 'difficulty' => 'medium', 'category' => 'habitat protection'],
            ],
            'polar_bear' => [
                ['title' => 'Reduce your carbon footprint', 'description' => 'Cut energy use and transportation emissions that drive the sea-ice loss polar bears depend on.', 'difficulty' => 'hard', 'category' => 'waste reduction'],
                ['title' => 'Support Arctic conservation research', 'description' => 'Follow and share findings from organisations monitoring polar bears and sea ice.', 'difficulty' => 'easy', 'category' => 'community'],
                ['title' => 'Choose efficient appliances', 'description' => 'Choose efficient appliances and renewable energy options at home and work.', 'difficulty' => 'medium', 'category' => 'waste reduction'],
            ],
            'wolf' => [
                ['title' => 'Learn about coexistence tools', 'description' => 'Learn how fencing, guard animals, and non-lethal methods reduce livestock losses to wolves.', 'difficulty' => 'medium', 'category' => 'community'],
                ['title' => 'Protect denning areas', 'description' => 'Respect closures and keep dogs out of areas where wolves may be denning.', 'difficulty' => 'easy', 'category' => 'habitat protection'],
                ['title' => 'Share balanced conservation information', 'description' => 'Promote science-based views of wolf ecology and management.', 'difficulty' => 'easy', 'category' => 'community'],
            ],
            'red_panda' => [
                ['title' => 'Choose bamboo-friendly brands', 'description' => 'Buy bamboo products from sources that do not degrade red panda forest habitat.', 'difficulty' => 'medium', 'category' => 'waste reduction'],
                ['title' => 'Support forest corridor projects', 'description' => 'Support work that reconnects the fragmented bamboo forests red pandas depend on.', 'difficulty' => 'hard', 'category' => 'habitat protection'],
                ['title' => 'Avoid buying exotic pets trade', 'description' => 'Learn why wild animals belong in the wild and report suspected wildlife trade.', 'difficulty' => 'easy', 'category' => 'community'],
            ],
            'penguin' => [
                ['title' => 'Choose responsible seafood', 'description' => 'Pick seafood from fisheries that avoid overfishing the prey penguins depend on.', 'difficulty' => 'medium', 'category' => 'waste reduction'],
                ['title' => 'Reduce plastic entering the sea', 'description' => 'Cut single-use plastic and join local clean-ups of coastlines and waterways.', 'difficulty' => 'easy', 'category' => 'waste reduction'],
                ['title' => 'Protect roosting shorelines', 'description' => 'Keep dogs and people away from nesting and moulting beaches.', 'difficulty' => 'easy', 'category' => 'habitat protection'],
            ],
            'pelican' => [
                ['title' => 'Protect wetland water levels', 'description' => 'Conserve water and support wetland restoration where pelicans nest and feed.', 'difficulty' => 'medium', 'category' => 'habitat protection'],
                ['title' => 'Keep fishing lines and hooks out of sites', 'description' => 'Dispose of fishing gear responsibly so water birds are not entangled.', 'difficulty' => 'easy', 'category' => 'waste reduction'],
                ['title' => 'Join coastal clean-up events', 'description' => 'Remove litter from shorelines before it can reach nesting and feeding grounds.', 'difficulty' => 'easy', 'category' => 'community'],
            ],
            'lion' => [
                ['title' => 'Support anti-poaching patrols', 'description' => 'Support groups that patrol protected areas and reduce snaring and poaching pressure.', 'difficulty' => 'hard', 'category' => 'habitat protection'],
                ['title' => 'Advocate for wildlife corridors', 'description' => 'Support land-use plans that keep lion habitat connected to prey-rich areas.', 'difficulty' => 'medium', 'category' => 'habitat protection'],
                ['title' => 'Share accurate lion facts', 'description' => 'Promote accurate information about lion populations and ecosystem roles.', 'difficulty' => 'easy', 'category' => 'community'],
            ],
            'lynx' => [
                ['title' => 'Keep forests connected', 'description' => 'Support habitat links that let lynx move between mountain ranges and forest blocks.', 'difficulty' => 'medium', 'category' => 'habitat protection'],
                ['title' => 'Learn non-lethal predator tools', 'description' => 'Learn how farmers prevent livestock losses without harming lynx populations.', 'difficulty' => 'easy', 'category' => 'community'],
                ['title' => 'Report lynx sightings responsibly', 'description' => 'Share verified sightings with local records schemes to support population monitoring.', 'difficulty' => 'easy', 'category' => 'community'],
            ],
            'wild_boar' => [
                ['title' => 'Secure farm food stores', 'description' => 'Store grain and food where wild boar cannot reach, reducing crop damage and conflict.', 'difficulty' => 'medium', 'category' => 'waste reduction'],
                ['title' => 'Protect wetland and forest edge habitats', 'description' => 'Keep natural cover along waterways that supports balanced wild boar habitat.', 'difficulty' => 'easy', 'category' => 'habitat protection'],
                ['title' => 'Share balanced population information', 'description' => 'Help communities understand effective, humane options for managing wild boar numbers.', 'difficulty' => 'easy', 'category' => 'community'],
            ],
            'koala' => [
                ['title' => 'Plant native eucalyptus', 'description' => 'Plant suitable native food trees in koala-friendly areas, guided by local advice.', 'difficulty' => 'medium', 'category' => 'tree planting'],
                ['title' => 'Protect roadside trees', 'description' => 'Support efforts to keep shade and food trees along movement corridors.', 'difficulty' => 'easy', 'category' => 'habitat protection'],
                ['title' => 'Prepare for fire season', 'description' => 'Learn how communities reduce bushfire risk while safeguarding koala habitat.', 'difficulty' => 'medium', 'category' => 'community'],
            ],
            'kangaroo' => [
                ['title' => 'Drive carefully in wildlife areas', 'description' => 'Slow down and watch for animals at dawn and dusk near bush and grassland.', 'difficulty' => 'easy', 'category' => 'habitat protection'],
                ['title' => 'Protect grassland and water sources', 'description' => 'Support land management that keeps dependable grass and watering points.', 'difficulty' => 'medium', 'category' => 'habitat protection'],
                ['title' => 'Fence responsibly', 'description' => 'Learn how wildlife-friendly fencing lets animals pass while keeping livestock safe.', 'difficulty' => 'easy', 'category' => 'community'],
            ],
            'giant_panda' => [
                ['title' => 'Support bamboo forest reserves', 'description' => 'Support protected areas that link bamboo stands mountain ranges.', 'difficulty' => 'hard', 'category' => 'habitat protection'],
                ['title' => 'Choose low-impact forest products', 'description' => 'Buy products that avoid driving logging in panda forest habitat.', 'difficulty' => 'medium', 'category' => 'waste reduction'],
                ['title' => 'Share panda conservation wins', 'description' => 'Highlight how corridor projects help connect isolated panda populations.', 'difficulty' => 'easy', 'category' => 'community'],
            ],
            'jaguar' => [
                ['title' => 'Support jungle corridor projects', 'description' => 'Support cross-border efforts that reconnect jaguar populations in the Americas.', 'difficulty' => 'hard', 'category' => 'habitat protection'],
                ['title' => 'Choose deforestation-free products', 'description' => 'Buy timber, beef, and paper that do not drive clearing of jaguar habitat.', 'difficulty' => 'medium', 'category' => 'waste reduction'],
                ['title' => 'Share river and wetland conservation facts', 'description' => 'Spread accurate information about the wetlands jaguars depend on.', 'difficulty' => 'easy', 'category' => 'community'],
            ],
            'gorilla' => [
                ['title' => 'Support great ape habitat protection', 'description' => 'Support ranger programmes that guard gorilla forests from poaching and clearing.', 'difficulty' => 'hard', 'category' => 'habitat protection'],
                ['title' => 'Respect health guidance near apes', 'description' => 'Follow health precautions that prevent disease passing between people and gorillas.', 'difficulty' => 'easy', 'category' => 'community'],
                ['title' => 'Choose responsible wood products', 'description' => 'Avoid timber that comes from gorilla range forests where possible.', 'difficulty' => 'medium', 'category' => 'waste reduction'],
            ],
            'otter' => [
                ['title' => 'Conserve clean water', 'description' => 'Use less water and avoid drain pollution that harms fish and otter habitat.', 'difficulty' => 'easy', 'category' => 'waste reduction'],
                ['title' => 'Protect riverbanks', 'description' => 'Keep natural banks and reeds that otters use for dens and movement.', 'difficulty' => 'medium', 'category' => 'habitat protection'],
                ['title' => 'Report pollution incidents', 'description' => 'Report oil or chemical spills near waterways to local authorities promptly.', 'difficulty' => 'easy', 'category' => 'community'],
            ],
            'leopard' => [
                ['title' => 'Support conflict-reduction schemes', 'description' => 'Support programmes that help herders protect livestock from leopards.', 'difficulty' => 'medium', 'category' => 'community'],
                ['title' => 'Keep natural cover connected', 'description' => 'Support plans that keep tree and rock cover linked across landscapes.', 'difficulty' => 'medium', 'category' => 'habitat protection'],
                ['title' => 'Avoid fuelling wildlife trade', 'description' => 'Learn how the illegal wildlife trade harms leopards and never buy leopard parts.', 'difficulty' => 'easy', 'category' => 'community'],
            ],
            'gazelle' => [
                ['title' => 'Support grassland restoration', 'description' => 'Support reseeding and grazing management that restores dryland grasses.', 'difficulty' => 'medium', 'category' => 'habitat protection'],
                ['title' => 'Protect seasonal water points', 'description' => 'Support measures that keep dry-season water available for migrating herds.', 'difficulty' => 'medium', 'category' => 'community'],
                ['title' => 'Advocate for wildlife-friendly fencing', 'description' => 'Learn how fencing options can let gazelles while protecting livestock.', 'difficulty' => 'easy', 'category' => 'community'],
            ],
        ];
    }
}
