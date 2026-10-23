<?php

namespace Tests\Feature;

use App\Models\ActionCompletion;
use App\Models\Animal;
use App\Models\User;
use Database\Seeders\AnimalExpansionSeeder;
use Database\Seeders\AnimalPetSeeder;
use Database\Seeders\AnimalSeeder;
use Database\Seeders\ConservationActionExpansionSeeder;
use Database\Seeders\ConservationActionPetSeeder;
use Database\Seeders\ConservationActionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardBadgeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            AnimalSeeder::class,
            AnimalExpansionSeeder::class,
            AnimalPetSeeder::class,
            ConservationActionSeeder::class,
            ConservationActionExpansionSeeder::class,
            ConservationActionPetSeeder::class,
        ]);
    }

    public function test_animals_covered_denominator_matches_the_scannable_library(): void
    {
        $user = User::factory()->create();
        $total = Animal::query()->scannable()->count();

        $this->assertSame(33, $total);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk()
            ->assertSee("0 / {$total}")
            ->assertSee('☆ Animal champion');
    }

    public function test_animal_champion_is_earned_when_every_supported_animal_is_covered(): void
    {
        $user = User::factory()->create();

        // One completed action per animal — the same coverage ActionCompletionSeeder
        // grants the demo user.
        Animal::query()->scannable()->each(function (Animal $animal) use ($user) {
            ActionCompletion::create([
                'user_id' => $user->id,
                'conservation_action_id' => $animal->conservationActions()->firstOrFail()->id,
                'completed_at' => now(),
            ]);
        });

        $total = Animal::query()->scannable()->count();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk()
            ->assertSee("{$total} / {$total}")
            ->assertSee('★ Animal champion');
    }

    public function test_animal_champion_is_not_earned_with_partial_coverage(): void
    {
        $user = User::factory()->create();
        $animal = Animal::query()->scannable()->firstOrFail();

        ActionCompletion::create([
            'user_id' => $user->id,
            'conservation_action_id' => $animal->conservationActions()->firstOrFail()->id,
            'completed_at' => now(),
        ]);

        $total = Animal::query()->scannable()->count();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk()
            ->assertSee("1 / {$total}")
            ->assertSee('☆ Animal champion');
    }
}
