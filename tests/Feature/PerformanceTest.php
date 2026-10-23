<?php

namespace Tests\Feature;

use App\Models\Animal;
use App\Models\Scan;
use App\Models\ScanFeedback;
use App\Models\ScannerEvent;
use App\Models\SurveyResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_performance_page_renders_metrics(): void
    {
        $user = User::factory()->create();
        $animal = Animal::factory()->create(['coco_label' => 'cat', 'common_name' => 'Cat']);
        $scanA = Scan::factory()->for($user)->for($animal)->create(['response_time_ms' => 200]);
        $scanB = Scan::factory()->for($user)->for($animal)->create(['response_time_ms' => 300]);
        ScanFeedback::factory()->create(['scan_id' => $scanA->id, 'user_id' => $user->id]);
        ScanFeedback::factory()->create(['scan_id' => $scanB->id, 'user_id' => $user->id, 'is_correct' => true]);
        ScannerEvent::factory()->create(['type' => 'low-confidence', 'user_id' => $user->id]);
        SurveyResponse::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('performance'));

        $response->assertStatus(200)
            ->assertSee('Performance overview')
            ->assertSee('Recognition accuracy')
            ->assertSee('Low-confidence rate');
    }

    public function test_performance_csv_streams_report_metrics(): void
    {
        $user = User::factory()->create();
        Scan::factory()->for($user)->create();

        $response = $this->actingAs($user)->get(route('performance.csv'));

        $response->assertStatus(200)
            ->assertHeader('content-type', 'text/csv; charset=utf-8')
            ->assertDownload();

        $this->assertStringContainsString('total_scans', $response->streamedContent());
    }

    public function test_scans_export_returns_only_own_rows(): void
    {
        $cat = Animal::factory()->create(['coco_label' => 'cat']);
        $dog = Animal::factory()->create(['coco_label' => 'dog']);

        $user = User::factory()->create();
        $other = User::factory()->create();

        Scan::factory()->for($user)->for($cat)->create(['detected_label' => 'cat']);
        Scan::factory()->for($other)->for($dog)->create(['detected_label' => 'dog']);

        $response = $this->actingAs($user)->get(route('scans.export'));

        $response->assertStatus(200)->assertDownload();

        $content = $response->streamedContent();
        $this->assertStringContainsString('cat', $content);
        $this->assertStringNotContainsString('dog', $content);
    }
}
