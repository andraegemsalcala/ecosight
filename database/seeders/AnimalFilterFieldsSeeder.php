<?php

namespace Database\Seeders;

use App\Models\Animal;
use Illuminate\Database\Seeder;

class AnimalFilterFieldsSeeder extends Seeder
{
    /**
     * The scan labels EcoSight can classify. The table also includes common
     * domestic animals that can be added later.
     *
     * @var array<string, array{class: string, domestic: bool}>
     */
    public const CLASSIFICATIONS = [
        'armadillo-chlamyphorus-truncatus' => ['class' => 'mammal', 'domestic' => false],
        'bear' => ['class' => 'mammal', 'domestic' => false],
        'brown_bear' => ['class' => 'mammal', 'domestic' => false],
        'cat' => ['class' => 'mammal', 'domestic' => true],
        'cheetah' => ['class' => 'mammal', 'domestic' => false],
        'common_fox' => ['class' => 'mammal', 'domestic' => false],
        'cow' => ['class' => 'mammal', 'domestic' => true],
        'dog' => ['class' => 'mammal', 'domestic' => true],
        'elephant' => ['class' => 'mammal', 'domestic' => false],
        'gazelle' => ['class' => 'mammal', 'domestic' => false],
        'giant_panda' => ['class' => 'mammal', 'domestic' => false],
        'giraffe' => ['class' => 'mammal', 'domestic' => false],
        'gorilla' => ['class' => 'mammal', 'domestic' => false],
        'horse' => ['class' => 'mammal', 'domestic' => true],
        'jaguar' => ['class' => 'mammal', 'domestic' => false],
        'kangaroo' => ['class' => 'mammal', 'domestic' => false],
        'koala' => ['class' => 'mammal', 'domestic' => false],
        'leopard' => ['class' => 'mammal', 'domestic' => false],
        'lion' => ['class' => 'mammal', 'domestic' => false],
        'lynx' => ['class' => 'mammal', 'domestic' => false],
        'otter' => ['class' => 'mammal', 'domestic' => false],
        'pet_guinea_pig' => ['class' => 'mammal', 'domestic' => true],
        'pet_hamster' => ['class' => 'mammal', 'domestic' => true],
        'pet_rabbit' => ['class' => 'mammal', 'domestic' => true],
        'polar_bear' => ['class' => 'mammal', 'domestic' => false],
        'red_panda' => ['class' => 'mammal', 'domestic' => false],
        'sheep' => ['class' => 'mammal', 'domestic' => true],
        'tiger' => ['class' => 'mammal', 'domestic' => false],
        'wild_boar' => ['class' => 'mammal', 'domestic' => false],
        'wolf' => ['class' => 'mammal', 'domestic' => false],
        'zebra' => ['class' => 'mammal', 'domestic' => false],
        'pig' => ['class' => 'mammal', 'domestic' => true],
        'goat' => ['class' => 'mammal', 'domestic' => true],
        'llama' => ['class' => 'mammal', 'domestic' => true],
        'alpaca' => ['class' => 'mammal', 'domestic' => true],
        'bird' => ['class' => 'bird', 'domestic' => false],
        'pelican' => ['class' => 'bird', 'domestic' => false],
        'penguin' => ['class' => 'bird', 'domestic' => false],
        'chicken' => ['class' => 'bird', 'domestic' => true],
        'duck' => ['class' => 'bird', 'domestic' => true],
        'goose' => ['class' => 'bird', 'domestic' => true],
    ];

    /**
     * @return array{class: string, domestic: bool}|null
     */
    public static function classificationFor(string $label): ?array
    {
        return self::CLASSIFICATIONS[$label] ?? null;
    }

    public function run(): void
    {
        Animal::query()
            ->orderBy('id')
            ->chunkById(100, function ($animals): void {
                foreach ($animals as $animal) {
                    $classification = self::classificationFor($animal->coco_label);
                    $updates = [];

                    if ($classification !== null) {
                        if (blank($animal->animal_class)) {
                            $updates['animal_class'] = $classification['class'];
                        }

                        if ($animal->is_domestic === null) {
                            $updates['is_domestic'] = $classification['domestic'];
                        }
                    }

                    if (blank($animal->status_category)) {
                        $updates['status_category'] = Animal::statusCategoryFromText($animal->conservation_status);
                    }

                    if ($updates !== []) {
                        $animal->forceFill($updates)->saveQuietly();
                    }
                }
            });
    }
}
