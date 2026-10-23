<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScannerEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_event_is_stored(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/scanner/event', [
            'type' => 'model-loaded',
            'model_load_ms' => 850,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('scanner_events', [
            'user_id' => $user->id,
            'type' => 'model-loaded',
            'model_load_ms' => 850,
        ]);
    }

    public function test_low_confidence_event_is_stored_with_timing(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/scanner/event', [
            'type' => 'low-confidence',
            'response_time_ms' => 1400,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('scanner_events', [
            'type' => 'low-confidence',
            'response_time_ms' => 1400,
        ]);
    }

    public function test_invalid_event_type_is_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/scanner/event', [
            'type' => 'bogus-event',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('type');
        $this->assertDatabaseCount('scanner_events', 0);
    }
}
