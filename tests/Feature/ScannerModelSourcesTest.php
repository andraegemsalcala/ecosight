<?php

namespace Tests\Feature;

use App\Models\Animal;
use App\Models\AnimalVariety;
use App\Models\User;
use Database\Seeders\AnimalExpansionSeeder;
use Database\Seeders\AnimalPetSeeder;
use Database\Seeders\AnimalSeeder;
use Database\Seeders\ConservationActionExpansionSeeder;
use Database\Seeders\ConservationActionPetSeeder;
use Database\Seeders\VarietySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ScannerModelSourcesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->seed([
            AnimalSeeder::class,
            AnimalExpansionSeeder::class,
            AnimalPetSeeder::class,
            ConservationActionExpansionSeeder::class,
            ConservationActionPetSeeder::class,
            VarietySeeder::class,
        ]);
    }

    public function test_labels_endpoint_lists_supported_classes_per_model(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/scanner/labels');

        // 23 mobilenet records; red fox and kit fox share one record, so the
        // distinct ImageNet class list holds 24 entries.
        $response->assertOk()
            ->assertJsonCount(10, 'coco')
            ->assertJsonCount(24, 'mobilenet')
            ->assertJsonPath('min_confidences.coco-ssd', 0.60)
            ->assertJsonPath('min_confidences.mobilenet', 0.40)
            ->assertJsonFragment(['tiger, Panthera tigris'])
            ->assertJsonFragment(['red fox, Vulpes vulpes'])
            ->assertJsonFragment(['hamster']);

        // The browser maps a raw MobileNet className through mobilenet_map:
        // lower-cased full class names and every comma synonym are keys.
        $map = $response->json('mobilenet_map');
        $this->assertIsArray($map);
        $this->assertNotEmpty($map);
        $this->assertSame('tiger', $map['tiger, panthera tigris'] ?? null);
        $this->assertSame('tiger', $map['panthera tigris'] ?? null);
        $this->assertSame('pet_guinea_pig', $map['guinea pig, cavia cobaya'] ?? null);
        $this->assertSame('pet_guinea_pig', $map['cavia cobaya'] ?? null);
        $this->assertArrayNotHasKey('coffee mug', $map);

        // Every mapped value must be a real scannable library label.
        $scannable = Animal::query()->scannable()->pluck('coco_label')->all();
        foreach ($map as $key => $cocoLabel) {
            $this->assertContains($cocoLabel, $scannable, "mobilenet_map key '{$key}' points at a non-scannable label");
        }

        // Friendly display names for scanner status messages, keyed by coco_label.
        $names = $response->json('label_names');
        $this->assertIsArray($names);
        $this->assertCount(33, $names);
        $this->assertSame('Guinea pig', $names['pet_guinea_pig'] ?? null);
        $this->assertSame('Red fox', $names['common_fox'] ?? null);
        $this->assertSame('Dog', $names['dog'] ?? null);
        $this->assertSame([], array_diff(array_keys($names), $scannable));
        $this->assertSame([], array_diff($scannable, array_keys($names)));

        // Second request hits the cache; the payload must still be plain JSON
        // arrays (a previous bug leaked Eloquent Collections into the cache).
        $cached = $this->actingAs($user)->getJson('/scanner/labels');

        $cached->assertOk()
            ->assertJsonCount(10, 'coco')
            ->assertJsonCount(24, 'mobilenet');
        $this->assertSame($map, $cached->json('mobilenet_map'));
        $this->assertSame($names, $cached->json('label_names'));
    }

    public function test_labels_endpoint_includes_clip_prompts_and_varieties(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/scanner/labels');

        $response->assertOk()
            ->assertJsonPath('version', 6)
            ->assertJsonPath('min_confidences.clip', 0.50)
            ->assertJsonCount(33, 'clip_prompts.animals')
            ->assertJsonCount(16, 'clip_prompts.varieties')
            ->assertJsonPath('clip_prompts.animals.dog', ['a photo of a dog', 'dog', 'a domestic dog'])
            ->assertJsonCount(6, 'varieties.dog')
            ->assertJsonCount(4, 'varieties.cat');

        $this->assertArrayHasKey('dog:Labrador Retriever', $response->json('clip_prompts.varieties'));

        $variety = $response->json('varieties.dog.0');
        $this->assertArrayHasKey('id', $variety);
        $this->assertArrayHasKey('label', $variety);
        $this->assertNotEmpty($variety['clip_names']);
        $this->assertDatabaseHas('animal_varieties', ['id' => $variety['id'], 'label' => $variety['label']]);
    }

    public function test_labels_endpoint_lists_the_library_animals_for_species_matching(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/scanner/labels');

        $response->assertOk()->assertJsonCount(33, 'library');

        $library = $response->json('library');
        $this->assertIsArray($library);
        $this->assertArrayHasKey('scientific_name', $library[0]);
        $this->assertArrayHasKey('common_name', $library[0]);

        $names = collect($library)->pluck('common_name', 'coco_label');
        $this->assertSame('Dog', $names['dog'] ?? null);
        $this->assertSame('Red fox', $names['common_fox'] ?? null);
    }

    public function test_labels_cache_is_invalidated_when_the_library_changes(): void
    {
        $user = User::factory()->create();

        $first = $this->actingAs($user)->getJson('/scanner/labels');
        $first->assertOk();
        $version = $first->json('library_version');
        $this->assertNotEmpty($version);

        // The cached payload is reused while the library is unchanged.
        $cached = $this->actingAs($user)->getJson('/scanner/labels');
        $this->assertSame($version, $cached->json('library_version'));

        Animal::query()->where('coco_label', 'dog')->update([
            'updated_at' => now()->addSecond(),
        ]);

        $after = $this->actingAs($user)->getJson('/scanner/labels');
        $after->assertOk();
        $this->assertNotSame($version, $after->json('library_version'));
    }

    public function test_clip_scan_saved_at_its_threshold_with_top_two(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/scanner/scan', [
            'detected_label' => 'dog',
            'model_source' => 'clip',
            'confidence' => 0.62,
            'top2_label' => 'bear',
            'top2_confidence' => 0.18,
            'response_time_ms' => 450,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('scans', [
            'model_source' => 'clip',
            'detected_label' => 'dog',
            'top2_label' => 'bear',
            'user_confirmed' => false,
            'variety_id' => null,
        ]);
    }

    public function test_clip_scan_below_threshold_requires_user_confirmation(): void
    {
        $user = User::factory()->create();

        $rejected = $this->actingAs($user)->postJson('/scanner/scan', [
            'detected_label' => 'dog',
            'model_source' => 'clip',
            'confidence' => 0.45,
            'response_time_ms' => 450,
        ]);

        $rejected->assertStatus(422)->assertJsonPath(
            'message',
            'Confidence 0.45 is below the CLIP threshold of 0.50.'
        );

        $confirmed = $this->actingAs($user)->postJson('/scanner/scan', [
            'detected_label' => 'dog',
            'model_source' => 'clip',
            'confidence' => 0.45,
            'user_confirmed' => true,
            'response_time_ms' => 450,
        ]);

        $confirmed->assertStatus(201);
        $this->assertDatabaseHas('scans', ['user_confirmed' => true, 'confidence' => 0.45]);

        $tooWeak = $this->actingAs($user)->postJson('/scanner/scan', [
            'detected_label' => 'dog',
            'model_source' => 'clip',
            'confidence' => 0.29,
            'user_confirmed' => true,
            'response_time_ms' => 450,
        ]);

        $tooWeak->assertStatus(422);
        $this->assertDatabaseCount('scans', 1);
    }

    public function test_clip_scan_stores_a_confirmed_variety_of_the_detected_animal(): void
    {
        $user = User::factory()->create();
        $dog = Animal::where('coco_label', 'dog')->firstOrFail();
        $variety = $dog->varieties()->where('label', 'Labrador Retriever')->firstOrFail();

        $response = $this->actingAs($user)->postJson('/scanner/scan', [
            'detected_label' => 'dog',
            'model_source' => 'clip',
            'confidence' => 0.72,
            'variety_label' => 'Labrador Retriever',
            'variety_confidence' => 0.61,
            'variety_confirmed' => true,
            'response_time_ms' => 520,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('variety.id', $variety->id);

        $this->assertDatabaseHas('scans', [
            'variety_id' => $variety->id,
            'variety_label' => 'Labrador Retriever',
            'variety_confirmed' => true,
        ]);
    }

    public function test_clip_scan_rejects_a_variety_from_another_animal(): void
    {
        $user = User::factory()->create();
        $this->assertTrue(AnimalVariety::where('label', 'Persian')->exists());

        $response = $this->actingAs($user)->postJson('/scanner/scan', [
            'detected_label' => 'dog',
            'model_source' => 'clip',
            'confidence' => 0.72,
            'variety_label' => 'Persian',
            'variety_confidence' => 0.61,
            'response_time_ms' => 520,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('scans', 0);
    }

    public function test_labels_endpoint_requires_authentication(): void
    {
        $this->getJson('/scanner/labels')->assertStatus(401);
    }

    public function test_mobilenet_scan_saved_for_image_net_label_at_its_threshold(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/scanner/scan', [
            'detected_label' => 'tiger, Panthera tigris',
            'model_source' => 'mobilenet',
            'confidence' => 0.45,
            'response_time_ms' => 90,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('animal.coco_label', 'tiger')
            ->assertJsonPath('animal.common_name', 'Tiger');

        $this->assertDatabaseHas('scans', [
            'user_id' => $user->id,
            'detected_label' => 'tiger, Panthera tigris',
            'confidence' => 0.45,
        ]);
    }

    public function test_mobilenet_scan_rejected_below_its_threshold(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/scanner/scan', [
            'detected_label' => 'tiger, Panthera tigris',
            'model_source' => 'mobilenet',
            'confidence' => 0.39,
            'response_time_ms' => 90,
        ]);

        $response->assertStatus(422)->assertJsonPath(
            'message',
            'Confidence 0.39 is below the MOBILENET threshold of 0.40.'
        );
        $this->assertDatabaseCount('scans', 0);
    }

    public function test_coco_source_cannot_skirt_threshold_with_mobilenet_label(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/scanner/scan', [
            'detected_label' => 'tiger, Panthera tigris',
            'model_source' => 'coco-ssd',
            'confidence' => 0.45,
            'response_time_ms' => 90,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('scans', 0);
    }

    public function test_lookup_resolves_an_image_net_label_to_its_record(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/scanner/animal/tiger%2C%20Panthera%20tigris');

        $response->assertOk()
            ->assertJsonPath('animal.common_name', 'Tiger')
            ->assertJsonCount(3, 'conservation_actions');
    }

    public function test_lookup_resolves_a_pet_record_from_its_image_net_label(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/scanner/animal/guinea%20pig%2C%20Cavia%20cobaya');

        $response->assertOk()
            ->assertJsonPath('animal.common_name', 'Guinea pig')
            ->assertJsonCount(3, 'conservation_actions');
    }

    public function test_lookup_returns_404_for_unknown_label(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/scanner/animal/not-an-animal')->assertStatus(404);
    }

    public function test_lookup_resolves_every_original_coco_label(): void
    {
        $user = User::factory()->create();

        $labels = Animal::query()
            ->scannable()
            ->where('model_source', 'coco-ssd')
            ->pluck('coco_label');

        $this->assertCount(10, $labels);

        foreach ($labels as $label) {
            $this->actingAs($user)
                ->getJson('/scanner/animal/'.urlencode($label))
                ->assertOk();
        }
    }

    public function test_lookup_resolves_every_mobilenet_library_label(): void
    {
        $user = User::factory()->create();

        $labels = Animal::query()
            ->scannable()
            ->where('model_source', 'mobilenet')
            ->pluck('coco_label');

        $this->assertNotEmpty($labels);

        foreach ($labels as $label) {
            $this->actingAs($user)
                ->getJson('/scanner/animal/'.urlencode($label))
                ->assertOk();
        }
    }
}
