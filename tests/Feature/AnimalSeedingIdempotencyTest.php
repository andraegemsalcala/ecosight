<?php

namespace Tests\Feature;

use App\Models\Animal;
use App\Models\ConservationAction;
use Database\Seeders\AnimalExpansionSeeder;
use Database\Seeders\AnimalPetSeeder;
use Database\Seeders\AnimalSeeder;
use Database\Seeders\ConservationActionExpansionSeeder;
use Database\Seeders\ConservationActionPetSeeder;
use Database\Seeders\ConservationActionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnimalSeedingIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private function seedOnce(): void
    {
        $this->seed([
            AnimalSeeder::class,
            AnimalExpansionSeeder::class,
            AnimalPetSeeder::class,
            ConservationActionSeeder::class,
            ConservationActionExpansionSeeder::class,
            ConservationActionPetSeeder::class,
        ]);
    }

    public function test_library_seeds_to_thirty_three_scannable_animals(): void
    {
        $this->seedOnce();

        $this->assertDatabaseCount('animals', 33);
        $this->assertSame(10, Animal::where('model_source', 'coco-ssd')->count());
        $this->assertSame(23, Animal::where('model_source', 'mobilenet')->count());
        $this->assertSame(33, Animal::where('is_scannable', true)->count());
        $this->assertCount(3, Animal::where('model_source', 'mobilenet')->first()->conservationActions);
    }

    public function test_seeders_are_idempotent_when_repeated(): void
    {
        $this->seedOnce();
        $beforeAnimals = Animal::count();
        $beforeActions = ConservationAction::count();

        $this->seedOnce();
        $this->seedOnce();

        $this->assertSame($beforeAnimals, Animal::count());
        $this->assertSame($beforeActions, ConservationAction::count());
    }

    public function test_each_mobilenet_animal_gets_three_conservation_actions(): void
    {
        $this->seedOnce();

        $incomplete = Animal::where('model_source', 'mobilenet')
            ->get()
            ->filter(fn (Animal $animal) => $animal->conservationActions()->count() < 3);

        $this->assertCount(0, $incomplete);
    }
}
