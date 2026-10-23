<?php

namespace Tests\Feature;

use App\Models\Animal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScanStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_scan_is_saved_for_a_supported_label(): void
    {
        $user = User::factory()->create();
        $animal = Animal::factory()->create(['coco_label' => 'elephant']);

        $response = $this->actingAs($user)->postJson('/scanner/scan', [
            'detected_label' => 'elephant',
            'model_source' => 'coco-ssd',
            'confidence' => 0.9,
            'response_time_ms' => 120,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('animal.id', $animal->id)
            ->assertJsonPath('scan_id', 1);

        $this->assertDatabaseHas('scans', [
            'user_id' => $user->id,
            'animal_id' => $animal->id,
            'detected_label' => 'elephant',
            'confidence' => 0.9,
            'response_time_ms' => 120,
        ]);
    }

    public function test_scan_rejected_below_confidence_threshold(): void
    {
        $user = User::factory()->create();
        Animal::factory()->create(['coco_label' => 'elephant']);

        $response = $this->actingAs($user)->postJson('/scanner/scan', [
            'detected_label' => 'elephant',
            'model_source' => 'coco-ssd',
            'confidence' => 0.59,
            'response_time_ms' => 120,
        ]);

        $response->assertStatus(422)->assertJsonPath('message', 'Confidence 0.59 is below the COCO-SSD threshold of 0.60.');
        $this->assertDatabaseCount('scans', 0);
    }

    public function test_scan_rejected_for_unsupported_label(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/scanner/scan', [
            'detected_label' => 'person',
            'model_source' => 'coco-ssd',
            'confidence' => 0.9,
            'response_time_ms' => 120,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('scans', 0);
    }
}
