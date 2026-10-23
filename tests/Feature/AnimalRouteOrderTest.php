<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnimalRouteOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_animals_create_resolves_before_show(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('animals.create'));

        $response->assertStatus(200);
    }
}
