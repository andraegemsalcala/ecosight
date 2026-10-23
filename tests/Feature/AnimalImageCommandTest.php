<?php

namespace Tests\Feature;

use App\Models\Animal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AnimalImageCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_command_fetches_a_missing_image_and_records_its_credit(): void
    {
        Storage::fake('public');
        $animal = Animal::factory()->create([
            'common_name' => 'Test tiger',
            'scientific_name' => 'Panthera testis',
        ]);
        $this->fakeWikimediaImage();

        $this->artisan('animals:fetch-images', ['--only' => (string) $animal->id])
            ->expectsOutputToContain('Updated 1, skipped 0, failed 0.')
            ->assertSuccessful();

        $animal->refresh();

        $this->assertNotNull($animal->image_path);
        $this->assertNull($animal->image_url);
        $this->assertSame('Jane Doe', $animal->image_credit);
        $this->assertSame('CC BY-SA 4.0', $animal->image_license);
        $this->assertSame('https://commons.wikimedia.org/wiki/File:Test_tiger.jpg', $animal->image_source_url);
        $this->assertNotNull($animal->image_reviewed_at);
        Storage::disk('public')->assertExists($animal->image_path);
    }

    public function test_existing_images_are_skipped_without_the_replace_option(): void
    {
        Storage::fake('public');
        $animal = Animal::factory()->create(['image_path' => 'animals/existing.webp']);
        Http::fake();

        $this->artisan('animals:fetch-images', ['--only' => (string) $animal->id])
            ->expectsOutput('No animals match the selected options.')
            ->assertSuccessful();

        Http::assertNothingSent();
        $this->assertSame('animals/existing.webp', $animal->fresh()->image_path);
    }

    public function test_a_dry_run_lists_animals_without_fetching_anything(): void
    {
        $animal = Animal::factory()->create();
        Http::fake();

        $this->artisan('animals:fetch-images', [
            '--only' => (string) $animal->id,
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('Dry run complete. 1 animal(s) would be processed.')
            ->assertSuccessful();

        Http::assertNothingSent();
        $this->assertNull($animal->fresh()->image_path);
    }

    private function fakeWikimediaImage(): void
    {
        $imageBytes = $this->jpegBytes();

        Http::fake([
            'https://en.wikipedia.org/w/api.php*' => Http::response([
                'query' => ['pages' => [
                    ['title' => 'Test tiger', 'index' => 1, 'pageimage' => 'Test_tiger.jpg'],
                ]],
            ]),
            'https://commons.wikimedia.org/w/api.php*' => Http::response([
                'query' => ['pages' => [[
                    'imageinfo' => [[
                        'mime' => 'image/jpeg',
                        'width' => 1200,
                        'height' => 800,
                        'thumburl' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/test.jpg/1200px-test.jpg',
                        'url' => 'https://upload.wikimedia.org/wikipedia/commons/test.jpg',
                        'descriptionurl' => 'https://commons.wikimedia.org/wiki/File:Test_tiger.jpg',
                        'extmetadata' => [
                            'LicenseShortName' => ['value' => 'CC BY-SA 4.0'],
                            'Artist' => ['value' => 'Jane Doe'],
                            'ImageDescription' => ['value' => 'A tiger walking'],
                        ],
                    ]],
                ]]],
            ]),
            'https://upload.wikimedia.org/*' => Http::response($imageBytes, 200, ['Content-Type' => 'image/jpeg']),
        ]);
    }

    private function jpegBytes(): string
    {
        $image = imagecreatetruecolor(600, 500);
        $color = imagecolorallocate($image, 40, 120, 70);
        imagefill($image, 0, 0, $color);

        ob_start();
        imagejpeg($image, null, 85);
        $contents = (string) ob_get_clean();
        imagedestroy($image);

        return $contents;
    }
}
