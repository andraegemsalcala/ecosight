<?php

namespace Database\Factories;

use App\Models\Animal;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Animal> */
class AnimalFactory extends Factory
{
    public function definition(): array
    {
        $label = fake()->unique()->word();

        return [
            'common_name' => fake()->unique()->word(),
            'scientific_name' => fake()->bothify('Species ?????????'),
            'coco_label' => $label,
            'model_source' => 'coco-ssd',
            'animal_class' => 'mammal',
            'is_domestic' => false,
            'model_labels' => [$label],
            'is_scannable' => true,
            'description' => fake()->paragraph(),
            'habitat' => fake()->sentence(),
            'climate_impact' => fake()->paragraph(),
            'conservation_status' => 'Least Concern',
            'image_url' => null,
            'image_path' => null,
            'image_alt' => null,
            'image_credit' => null,
            'image_license' => null,
            'image_source_url' => null,
            'image_reviewed_at' => null,
            'origin' => 'seeded',
            'is_reviewed' => true,
        ];
    }

    public function domestic(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_domestic' => true,
            'conservation_status' => 'Domesticated',
        ]);
    }

    public function bird(): static
    {
        return $this->state(fn (array $attributes) => [
            'animal_class' => 'bird',
        ]);
    }

    public function fromScan(): static
    {
        return $this->state(fn (array $attributes) => [
            'model_source' => 'clip',
            'origin' => 'user-added',
            'is_reviewed' => false,
            'wikidata_qid' => fake()->unique()->numerify('Q#######'),
            'source_url' => 'https://www.wikidata.org/wiki/'.($attributes['wikidata_qid'] ?? 'Q1'),
        ]);
    }

    public function unreviewed(): static
    {
        return $this->state(fn (array $attributes) => [
            'origin' => 'user-added',
            'is_reviewed' => false,
        ]);
    }

    public function mobilenet(): static
    {
        return $this->state(fn (array $attributes) => [
            'model_source' => 'mobilenet',
            'model_labels' => [$attributes['coco_label']],
        ]);
    }
}
