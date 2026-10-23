<?php

namespace Database\Factories;

use App\Models\Animal;
use App\Models\AnimalVariety;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AnimalVariety> */
class AnimalVarietyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'animal_id' => Animal::factory(),
            'label' => ucfirst(fake()->unique()->words(2, true)),
            'kind' => 'breed',
            'clip_names' => null,
        ];
    }

    public function withClipNames(): static
    {
        return $this->state(fn (array $attributes) => [
            'clip_names' => [
                'a photo of a '.strtolower($attributes['label']),
                $attributes['label'],
            ],
        ]);
    }
}
