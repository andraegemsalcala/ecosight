<?php

namespace Tests\Feature;

use App\Models\ConservationAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_authenticated_pages(): void
    {
        foreach ([['dashboard', []], ['scanner', []], ['scans.index', []], ['survey.create', []], ['performance', []]] as [$route, $params]) {
            $response = $this->get(route($route, $params ?? []));
            $response->assertRedirect(route('login'));
        }
    }

    public function test_guests_are_redirected_from_scans_export(): void
    {
        $this->get(route('scans.export'))->assertRedirect(route('login'));
    }

    public function test_guests_are_redirected_from_action_completion(): void
    {
        $action = ConservationAction::factory()->create();

        $this->post(route('actions.complete', $action))->assertRedirect(route('login'));
        $this->delete(route('actions.complete.destroy', $action))->assertRedirect(route('login'));
    }
}
