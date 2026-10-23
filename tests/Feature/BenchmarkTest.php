<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BenchmarkTest extends TestCase
{
    use RefreshDatabase;

    public function test_benchmark_page_requires_authentication(): void
    {
        $this->get('/scanner/benchmark')->assertRedirect('/login');
    }

    public function test_benchmark_page_is_only_available_in_debug_builds(): void
    {
        $user = User::factory()->create();

        config(['app.debug' => false]);
        $this->actingAs($user)->get('/scanner/benchmark')->assertNotFound();

        config(['app.debug' => true]);
        $this->actingAs($user)->get('/scanner/benchmark')
            ->assertOk()
            ->assertSee('Recogniser benchmark');
    }
}
