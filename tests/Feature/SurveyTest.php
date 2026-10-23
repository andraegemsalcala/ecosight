<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SurveyTest extends TestCase
{
    use RefreshDatabase;

    public function test_survey_is_stored_and_redirects_to_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/survey', [
            'ease_rating' => 4,
            'speed_rating' => 5,
            'clarity_rating' => 4,
            'usefulness_rating' => 5,
            'comment' => 'Loved the scanner!',
        ]);

        $response->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('survey_responses', [
            'user_id' => $user->id,
            'ease_rating' => 4,
            'speed_rating' => 5,
            'clarity_rating' => 4,
            'usefulness_rating' => 5,
            'comment' => 'Loved the scanner!',
        ]);
    }

    public function test_survey_with_missing_ratings_fails_validation(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/survey', [
            'ease_rating' => 4,
            'comment' => 'Incomplete.',
        ]);

        $response->assertSessionHasErrors(['speed_rating', 'clarity_rating', 'usefulness_rating']);
        $this->assertDatabaseCount('survey_responses', 0);
    }
}
