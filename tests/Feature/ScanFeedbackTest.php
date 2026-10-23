<?php

namespace Tests\Feature;

use App\Models\Scan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScanFeedbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_feedback_is_stored_for_own_scan(): void
    {
        $user = User::factory()->create();
        $scan = Scan::factory()->for($user)->create();

        $response = $this->actingAs($user)->postJson('/scans/feedback', [
            'scan_id' => $scan->id,
            'is_correct' => true,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('scan_feedback', [
            'scan_id' => $scan->id,
            'user_id' => $user->id,
            'is_correct' => true,
        ]);
    }

    public function test_feedback_is_rejected_for_another_users_scan(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $scan = Scan::factory()->for($owner)->create();

        $response = $this->actingAs($other)->postJson('/scans/feedback', [
            'scan_id' => $scan->id,
            'is_correct' => false,
            'actual_label' => 'cat',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseCount('scan_feedback', 0);
    }

    public function test_feedback_accepts_an_actual_label_from_the_allowed_list(): void
    {
        $user = User::factory()->create();
        $scan = Scan::factory()->for($user)->create();

        $response = $this->actingAs($user)->postJson('/scans/feedback', [
            'scan_id' => $scan->id,
            'is_correct' => false,
            'actual_label' => 'cat',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('scan_feedback', [
            'scan_id' => $scan->id,
            'is_correct' => false,
            'actual_label' => 'cat',
        ]);
    }
}
