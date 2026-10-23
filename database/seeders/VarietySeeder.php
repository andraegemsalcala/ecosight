<?php

namespace Database\Seeders;

use App\Models\Animal;
use Illuminate\Database\Seeder;

/**
 * Curated, practically recognisable varieties (mostly breeds) for the
 * animals where a second CLIP decision is useful. Idempotent: varieties are
 * matched on (animal_id, label) so admin edits to other varieties survive.
 *
 * `clip_names` holds the CLIP text prompts for the variety; when null the
 * client falls back to the label itself.
 */
class VarietySeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->varieties() as $cocoLabel => $varieties) {
            $animal = Animal::where('coco_label', $cocoLabel)->first();
            if (! $animal) {
                continue;
            }

            foreach ($varieties as $variety) {
                $animal->varieties()->updateOrCreate(
                    ['label' => $variety['label']],
                    ['clip_names' => $variety['clip_names']],
                );
            }
        }
    }

    /**
     * @return array<string, array<int, array{label: string, clip_names: string[]}>>
     */
    private function varieties(): array
    {
        return [
            'dog' => [
                ['label' => 'Labrador Retriever', 'clip_names' => ['a photo of a labrador retriever dog', 'a labrador retriever']],
                ['label' => 'German Shepherd', 'clip_names' => ['a photo of a german shepherd dog', 'a german shepherd']],
                ['label' => 'Golden Retriever', 'clip_names' => ['a photo of a golden retriever dog', 'a golden retriever']],
                ['label' => 'Beagle', 'clip_names' => ['a photo of a beagle dog', 'a beagle']],
                ['label' => 'Poodle', 'clip_names' => ['a photo of a poodle dog', 'a poodle']],
                ['label' => 'Siberian Husky', 'clip_names' => ['a photo of a siberian husky dog', 'a husky']],
            ],
            'cat' => [
                ['label' => 'Persian', 'clip_names' => ['a photo of a persian cat', 'a persian cat']],
                ['label' => 'Siamese', 'clip_names' => ['a photo of a siamese cat', 'a siamese cat']],
                ['label' => 'Maine Coon', 'clip_names' => ['a photo of a maine coon cat', 'a maine coon cat']],
                ['label' => 'Bengal', 'clip_names' => ['a photo of a bengal cat', 'a bengal cat']],
            ],
            'horse' => [
                ['label' => 'Thoroughbred', 'clip_names' => ['a photo of a thoroughbred horse', 'a thoroughbred racehorse']],
                ['label' => 'Arabian', 'clip_names' => ['a photo of an arabian horse', 'an arabian horse']],
                ['label' => 'Shetland pony', 'clip_names' => ['a photo of a shetland pony', 'a small pony']],
            ],
            'cow' => [
                ['label' => 'Holstein Friesian', 'clip_names' => ['a photo of a holstein cow', 'a black and white dairy cow']],
                ['label' => 'Aberdeen Angus', 'clip_names' => ['a photo of an angus cow', 'a black beef cow']],
                ['label' => 'Jersey', 'clip_names' => ['a photo of a jersey cow', 'a light brown dairy cow']],
            ],
        ];
    }
}
