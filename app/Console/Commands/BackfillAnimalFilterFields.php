<?php

namespace App\Console\Commands;

use App\Models\Animal;
use Database\Seeders\AnimalFilterFieldsSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('animals:backfill-filter-fields {--dry-run : Report changes without writing them}')]
#[Description('Fill missing animal classification and conservation category fields')]
class BackfillAnimalFilterFields extends Command
{
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $beforeMissing = [
            'class' => Animal::whereNull('animal_class')->count(),
            'domestic' => Animal::whereNull('is_domestic')->count(),
            'category' => Animal::whereNull('status_category')->count(),
        ];
        $beforeCategories = $this->categoryCounts();
        $projectedCategories = $beforeCategories;
        $updates = 0;
        $unclassified = [];

        Animal::query()
            ->orderBy('id')
            ->chunkById(100, function ($animals) use (&$updates, &$unclassified, &$projectedCategories, $dryRun): void {
                foreach ($animals as $animal) {
                    $classification = AnimalFilterFieldsSeeder::classificationFor($animal->coco_label);
                    $changes = [];

                    if ($classification !== null) {
                        if (blank($animal->animal_class)) {
                            $changes['animal_class'] = $classification['class'];
                        }

                        if ($animal->is_domestic === null) {
                            $changes['is_domestic'] = $classification['domestic'];
                        }
                    } else {
                        $unclassified[] = $animal->common_name;
                    }

                    if (blank($animal->status_category)) {
                        $category = Animal::statusCategoryFromText($animal->conservation_status);
                        $changes['status_category'] = $category;

                        $projectedCategories['missing'] = max(0, ($projectedCategories['missing'] ?? 0) - 1);
                        $projectedCategories[$category] = ($projectedCategories[$category] ?? 0) + 1;
                    }

                    if ($changes !== []) {
                        $updates++;

                        if (! $dryRun) {
                            $animal->forceFill($changes)->saveQuietly();
                        }
                    }
                }
            });

        $afterMissing = $dryRun
            ? [
                'class' => $beforeMissing['class'] - $this->projectedClassFills(),
                'domestic' => $beforeMissing['domestic'] - $this->projectedDomesticFills(),
                'category' => $beforeMissing['category'] - $this->projectedCategoryFills(),
            ]
            : [
                'class' => Animal::whereNull('animal_class')->count(),
                'domestic' => Animal::whereNull('is_domestic')->count(),
                'category' => Animal::whereNull('status_category')->count(),
            ];

        $this->table(
            ['Field', 'Before', 'After'],
            [
                ['Missing animal class', $beforeMissing['class'], $afterMissing['class']],
                ['Missing domestic flag', $beforeMissing['domestic'], $afterMissing['domestic']],
                ['Missing status category', $beforeMissing['category'], $afterMissing['category']],
            ],
        );

        $this->newLine();
        $this->line($dryRun ? 'Category counts before:' : 'Category counts after:');
        $this->table(
            ['Category', 'Animals'],
            collect($dryRun ? $beforeCategories : $this->categoryCounts())
                ->map(fn (int $count, string $category): array => [$category, $count])
                ->values()
                ->all(),
        );

        if ($unclassified !== []) {
            $this->newLine();
            $this->warn('Unclassified animals: '.implode(', ', array_unique($unclassified)));
        }

        $this->newLine();
        $this->info($dryRun
            ? "Dry run complete. {$updates} animal record(s) would be updated."
            : "Backfill complete. {$updates} animal record(s) updated.");

        return self::SUCCESS;
    }

    private function projectedClassFills(): int
    {
        return Animal::query()
            ->whereNull('animal_class')
            ->pluck('coco_label')
            ->filter(fn (string $label): bool => AnimalFilterFieldsSeeder::classificationFor($label) !== null)
            ->count();
    }

    private function projectedDomesticFills(): int
    {
        return Animal::query()
            ->whereNull('is_domestic')
            ->pluck('coco_label')
            ->filter(fn (string $label): bool => AnimalFilterFieldsSeeder::classificationFor($label) !== null)
            ->count();
    }

    private function projectedCategoryFills(): int
    {
        return Animal::whereNull('status_category')->count();
    }

    /**
     * @return array<string, int>
     */
    private function categoryCounts(): array
    {
        return Animal::query()
            ->selectRaw('status_category, COUNT(*) as aggregate')
            ->groupBy('status_category')
            ->orderBy('status_category')
            ->pluck('aggregate', 'status_category')
            ->mapWithKeys(fn (int $count, ?string $category): array => [
                $category ?: 'missing' => $count,
            ])
            ->all();
    }
}
