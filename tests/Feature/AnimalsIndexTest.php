<?php

namespace Tests\Feature;

use Database\Seeders\AnimalExpansionSeeder;
use Database\Seeders\AnimalFilterFieldsSeeder;
use Database\Seeders\AnimalPetSeeder;
use Database\Seeders\AnimalSeeder;
use App\Models\Animal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnimalsIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            AnimalSeeder::class,
            AnimalExpansionSeeder::class,
            AnimalPetSeeder::class,
            AnimalFilterFieldsSeeder::class,
        ]);
    }

    public function test_empty_filters_return_every_animal_paginated_at_twelve(): void
    {
        $response = $this->get('/animals');

        $response->assertOk();
        $this->assertSame(33, $response->viewData('animals')->total());
        $this->assertSame(12, $response->viewData('animals')->count());
        $response->assertSee('Bear')->assertSee('Gazelle');
    }

    public function test_search_matches_common_name(): void
    {
        $animals = $this->get('/animals?search=giraffe')->viewData('animals');

        $this->assertSame(1, $animals->total());
        $this->assertSame('Giraffe', $animals->first()->common_name);
    }

    public function test_search_matches_scientific_name(): void
    {
        $animals = $this->get('/animals?search=panthera%20tigris')->viewData('animals');

        $this->assertSame(1, $animals->total());
        $this->assertSame('tiger', $animals->first()->coco_label);
    }

    public function test_search_matches_scan_label(): void
    {
        $animals = $this->get('/animals?search=pet_guinea_pig')->viewData('animals');

        $this->assertSame(1, $animals->total());
        $this->assertSame('Guinea pig', $animals->first()->common_name);
    }

    public function test_status_filter_returns_only_matching_animals(): void
    {
        $animals = $this->get('/animals?status=Endangered')->viewData('animals');

        $this->assertGreaterThan(0, $animals->total());
        foreach ($animals as $animal) {
            $this->assertSame('Endangered', $animal->conservation_status);
        }
    }

    public function test_unknown_status_filter_does_not_hide_everything(): void
    {
        $response = $this->get('/animals?status=Not%20A%20Real%20Status');

        $response->assertOk();
        $this->assertSame(33, $response->viewData('animals')->total());
    }

    public function test_like_wildcards_match_literally_instead_of_everything(): void
    {
        // No record contains a literal "%", so it must match nothing (not everything).
        $this->assertSame(0, $this->get('/animals?search=%25')->viewData('animals')->total());

        // "_" matches literally: only scan labels that really contain an
        // underscore (pet_guinea_pig, brown_bear, …), not every animal.
        $underscore = $this->get('/animals?search=_')->viewData('animals');
        $this->assertGreaterThan(0, $underscore->total());
        $this->assertLessThan(33, $underscore->total());
        foreach ($underscore as $animal) {
            $this->assertStringContainsString('_', $animal->coco_label);
        }
    }

    public function test_status_filter_reveals_conservation_categories_and_distinct_legacy_values(): void
    {
        $response = $this->get('/animals');

        $statuses = $response->viewData('conservationStatuses');
        $this->assertContains('Endangered', $statuses->all());
        $this->assertSame($statuses->unique()->count(), $statuses->count());
        $response->assertSee('Conservation category');
        $response->assertSee('Critically Endangered');
    }

    public function test_class_filter_and_facets_use_the_same_search_scope(): void
    {
        $response = $this->get('/animals?class[]=bird');
        $animals = $response->viewData('animals');

        $this->assertGreaterThan(0, $animals->total());
        foreach ($animals as $animal) {
            $this->assertSame('bird', $animal->animal_class);
        }

        $birdFacet = collect($response->viewData('classFacets'))->firstWhere('value', 'bird');
        $this->assertSame($animals->total(), $birdFacet['count']);
        $this->assertSame(
            $animals->total(),
            collect($response->viewData('statusFacets'))->sum('count'),
        );
    }

    public function test_multiple_canonical_filters_combine(): void
    {
        $animals = $this->get('/animals?class[]=mammal&status[]=endangered&kind=wild')
            ->viewData('animals');

        $this->assertGreaterThan(0, $animals->total());
        foreach ($animals as $animal) {
            $this->assertSame('mammal', $animal->animal_class);
            $this->assertSame('endangered', $animal->status_category);
            $this->assertFalse($animal->is_domestic);
        }
    }

    public function test_invalid_filter_values_are_ignored(): void
    {
        $response = $this->get('/animals?class[]=fish&status[]=bogus&kind=alien&image=maybe&scan=no&sort=weird');

        $response->assertOk();
        $this->assertSame(33, $response->viewData('animals')->total());
    }

    public function test_kind_scan_image_source_review_and_type_filters_work(): void
    {
        $this->assertSame(
            Animal::where('is_domestic', true)->count(),
            $this->get('/animals?kind=domestic')->viewData('animals')->total(),
        );
        $this->assertSame(
            Animal::where('is_scannable', false)->count(),
            $this->get('/animals?scan=library-only')->viewData('animals')->total(),
        );
        $this->assertSame(
            Animal::whereNotNull('image_path')->orWhereNotNull('image_url')->count(),
            $this->get('/animals?image=with')->viewData('animals')->total(),
        );
        $this->assertSame(
            Animal::where('origin', 'seeded')->count(),
            $this->get('/animals?source=seeded')->viewData('animals')->total(),
        );
        $this->assertSame(
            Animal::where('is_reviewed', false)->count(),
            $this->get('/animals?review=unreviewed')->viewData('animals')->total(),
        );

        $typed = Animal::factory()->create();
        $typed->varieties()->create(['label' => 'Test breed', 'kind' => 'breed']);

        $this->assertSame(1, $this->get('/animals?types=with')->viewData('animals')->total());
        $this->assertSame(33, $this->get('/animals?types=without')->viewData('animals')->total());
    }

    public function test_image_filter_treats_path_or_url_as_having_an_image(): void
    {
        $withPath = Animal::factory()->create(['image_path' => 'animals/path.webp']);
        $withUrl = Animal::factory()->create(['image_url' => 'https://example.com/photo.jpg']);

        $animals = $this->get('/animals?image=with')->viewData('animals');

        $this->assertSame(2, $animals->total());
        $this->assertTrue($animals->pluck('id')->contains($withPath->id));
        $this->assertTrue($animals->pluck('id')->contains($withUrl->id));
        $this->assertSame(33, $this->get('/animals?image=without')->viewData('animals')->total());
    }

    public function test_newest_and_status_sorts(): void
    {
        $newest = Animal::factory()->create(['common_name' => 'Aardvark test', 'created_at' => now()->addDay()]);

        $this->assertSame(
            $newest->id,
            $this->get('/animals?sort=newest')->viewData('animals')->first()->id,
        );

        $sorted = $this->get('/animals?sort=status')->viewData('animals')->pluck('status_category')->all();
        $ranks = array_map(
            fn (string $status): int => \App\Enums\AnimalStatusCategory::from($status)->sortRank(),
            $sorted,
        );

        $this->assertSame($ranks, collect($ranks)->sort()->values()->all());
    }

    public function test_search_facets_respect_search_but_not_their_own_group(): void
    {
        $response = $this->get('/animals?q=tiger');
        $animals = $response->viewData('animals');

        $this->assertSame(1, $animals->total());
        $this->assertSame(
            1,
            collect($response->viewData('classFacets'))->firstWhere('value', 'mammal')['count'],
        );
        $this->assertSame(1, collect($response->viewData('statusFacets'))->sum('count'));
    }

    public function test_active_filters_have_remove_urls(): void
    {
        $response = $this->get('/animals?search=giraffe&class[]=mammal&image=with');
        $filters = $response->viewData('activeFilters');

        $this->assertCount(3, $filters);
        $this->assertStringContainsString('Search:', $filters[0]['label']);
        $this->assertStringContainsString('image=with', $filters[1]['remove_url']);
        $this->assertStringNotContainsString('image=with', $filters[2]['remove_url']);
    }
}
