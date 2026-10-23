<?php

namespace Tests\Feature;

use App\Models\ConservationAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActionCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_action_is_completed_and_unique(): void
    {
        $user = User::factory()->create();
        $action = ConservationAction::factory()->create();

        $this->actingAs($user)->post(route('actions.complete', $action));
        $this->actingAs($user)->post(route('actions.complete', $action));

        $this->assertDatabaseCount('action_completions', 1);
        $this->assertDatabaseHas('action_completions', [
            'user_id' => $user->id,
            'conservation_action_id' => $action->id,
        ]);
    }

    public function test_action_completion_is_removed(): void
    {
        $user = User::factory()->create();
        $action = ConservationAction::factory()->create();

        $this->actingAs($user)->post(route('actions.complete', $action));
        $this->actingAs($user)->delete(route('actions.complete.destroy', $action));

        $this->assertDatabaseCount('action_completions', 0);
    }
}
