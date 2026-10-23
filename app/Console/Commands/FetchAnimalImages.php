<?php

namespace App\Console\Commands;

use App\Models\Animal;
use App\Services\AnimalImageService;
use App\Services\WikipediaImageService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('animals:fetch-images
    {--only= : Comma-separated animal IDs}
    {--missing : Only animals without an image (default)}
    {--replace : Replace existing images}
    {--dry-run : Report the animals without fetching images}')]
#[Description('Fetch freely licensed Wikimedia Commons images for animals')]
class FetchAnimalImages extends Command
{
    public function handle(WikipediaImageService $wikipedia, AnimalImageService $images): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $replace = (bool) $this->option('replace');
        $missing = (bool) $this->option('missing');

        if ($replace && $missing) {
            $this->error('The --replace and --missing options cannot be used together.');

            return self::FAILURE;
        }

        $ids = $this->onlyIds();

        if ($ids === null) {
            return self::FAILURE;
        }

        $query = Animal::query()->orderBy('id');

        if ($ids !== []) {
            $query->whereIn('id', $ids);
        }

        if (! $replace) {
            $query->withoutImage();
        }

        $animals = $query->get();

        if ($animals->isEmpty()) {
            $this->info('No animals match the selected options.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->table(
                ['ID', 'Animal'],
                $animals->map(fn (Animal $animal): array => [$animal->id, $animal->common_name])->all(),
            );
            $this->info("Dry run complete. {$animals->count()} animal(s) would be processed.");

            return self::SUCCESS;
        }

        $rows = [];
        $updated = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($animals as $index => $animal) {
            try {
                $candidate = $wikipedia->candidateFor($animal);

                if ($candidate === null) {
                    $rows[] = [$animal->id, $animal->common_name, 'No freely licensed image found'];
                    $skipped++;
                } else {
                    $contents = $wikipedia->download($candidate);

                    $images->storeBinary($contents, $animal, [
                        'alt' => $candidate['alt'] ?? null,
                        'credit' => $candidate['credit'] ?? null,
                        'license' => $candidate['license'] ?? null,
                        'source_url' => $candidate['source_url'] ?? null,
                        'reviewed' => true,
                    ]);

                    $rows[] = [$animal->id, $animal->common_name, 'Updated'];
                    $updated++;
                }
            } catch (RuntimeException $exception) {
                $rows[] = [$animal->id, $animal->common_name, $exception->getMessage()];
                $failed++;
            }

            if ($index < $animals->count() - 1) {
                usleep(300_000);
            }
        }

        $this->table(['ID', 'Animal', 'Result'], $rows);
        $this->newLine();
        $this->info("Updated {$updated}, skipped {$skipped}, failed {$failed}.");

        return $failed > 0 && $updated === 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return list<int>|null
     */
    private function onlyIds(): ?array
    {
        $only = trim((string) $this->option('only'));

        if ($only === '') {
            return [];
        }

        $ids = array_map('trim', explode(',', $only));

        foreach ($ids as $id) {
            if ($id === '' || ! ctype_digit($id) || (int) $id < 1) {
                $this->error('The --only option must be a comma-separated list of animal IDs.');

                return null;
            }
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }
}
