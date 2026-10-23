<?php

namespace Tests\Feature;

use App\Models\Animal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AnimalImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_uploaded_images_are_converted_to_local_webp_and_metadata_is_saved(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $animal = Animal::factory()->create();

        $response = $this->actingAs($user)->post(route('animals.image.store', $animal), [
            'image' => UploadedFile::fake()->image('animal.jpg', 800, 600),
            'image_alt' => 'A test animal in grass',
            'image_credit' => 'Test photographer',
            'image_license' => 'CC BY 4.0',
        ]);

        $response->assertRedirect();
        $animal->refresh();

        $this->assertNotNull($animal->image_path);
        $this->assertStringStartsWith('animals/', $animal->image_path);
        $this->assertStringEndsWith('.webp', $animal->image_path);
        $this->assertSame('A test animal in grass', $animal->image_alt);
        $this->assertSame('Test photographer', $animal->image_credit);
        $this->assertSame('CC BY 4.0', $animal->image_license);
        $this->assertNotNull($animal->image_reviewed_at);
        Storage::disk('public')->assertExists($animal->image_path);
    }

    public function test_small_images_are_rejected(): void
    {
        Storage::fake('public');
        $animal = Animal::factory()->create();

        $response = $this->actingAs(User::factory()->create())
            ->post(route('animals.image.store', $animal), [
                'image' => UploadedFile::fake()->image('small.jpg', 300, 300),
            ]);

        $response->assertSessionHasErrors('image');
        $this->assertNull($animal->fresh()->image_path);
    }

    public function test_replacing_and_removing_an_image_cleans_up_files_and_metadata(): void
    {
        Storage::fake('public');
        $animal = Animal::factory()->create(['image_url' => 'https://example.com/old.jpg']);
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('animals.image.store', $animal), [
            'image' => UploadedFile::fake()->image('first.jpg', 800, 600),
        ]);

        $firstPath = $animal->fresh()->image_path;

        $this->actingAs($user)->post(route('animals.image.store', $animal), [
            'image' => UploadedFile::fake()->image('second.jpg', 800, 600),
        ]);

        $animal->refresh();

        $this->assertNotSame($firstPath, $animal->image_path);
        $this->assertNull($animal->image_url);
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($animal->image_path);
        $secondPath = $animal->image_path;

        $this->actingAs($user)
            ->delete(route('animals.image.destroy', $animal))
            ->assertRedirect();

        $animal->refresh();

        $this->assertNull($animal->image_path);
        $this->assertNull($animal->image_alt);
        $this->assertNull($animal->image_credit);
        $this->assertNull($animal->image_license);
        $this->assertNull($animal->image_source_url);
        Storage::disk('public')->assertMissing($secondPath);
    }

    public function test_deleting_an_animal_removes_its_image_file(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('animals/delete-me.webp', 'image');
        $animal = Animal::factory()->create(['image_path' => 'animals/delete-me.webp']);

        $this->actingAs(User::factory()->create())
            ->delete(route('animals.destroy', $animal))
            ->assertRedirect(route('animals.index'));

        $this->assertDatabaseMissing('animals', ['id' => $animal->id]);
        Storage::disk('public')->assertMissing('animals/delete-me.webp');
    }

    public function test_wikimedia_candidate_is_previewed_and_downloaded_only_after_confirmation(): void
    {
        Storage::fake('public');
        $animal = Animal::factory()->create(['common_name' => 'Test tiger', 'scientific_name' => 'Panthera testis']);
        $user = User::factory()->create();
        $imageBytes = $this->jpegBytes(1200, 800);

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
                            'Artist' => ['value' => '<a href="#">Jane Doe</a>'],
                            'ImageDescription' => ['value' => 'A tiger walking'],
                        ],
                    ]],
                ]]],
            ]),
            'https://upload.wikimedia.org/*' => Http::response($imageBytes, 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $candidate = $this->actingAs($user)->get(route('animals.image.candidate', $animal));

        $candidate->assertOk();
        $candidate->assertSee('Jane Doe');
        $candidate->assertSee('CC BY-SA 4.0');
        $candidate->assertSee('Preview shown directly from Wikimedia Commons');

        $token = $candidate->viewData('candidate')['token'];
        $this->assertNull($animal->fresh()->image_path);

        $this->actingAs($user)->post(route('animals.image.from-wikipedia', $animal), [
            'candidate_token' => $token,
            'image_alt' => 'Tiger walking through grass',
        ])->assertRedirect(route('animals.show', $animal));

        $animal->refresh();

        $this->assertNotNull($animal->image_path);
        $this->assertSame('Tiger walking through grass', $animal->image_alt);
        $this->assertSame('Jane Doe', $animal->image_credit);
        $this->assertSame('CC BY-SA 4.0', $animal->image_license);
        $this->assertSame('https://commons.wikimedia.org/wiki/File:Test_tiger.jpg', $animal->image_source_url);
        $this->assertNotNull($animal->image_reviewed_at);
        Storage::disk('public')->assertExists($animal->image_path);
    }

    public function test_noncommercial_wikimedia_images_are_rejected(): void
    {
        $animal = Animal::factory()->create();
        $user = User::factory()->create();

        Http::fake([
            'https://en.wikipedia.org/w/api.php*' => Http::response([
                'query' => ['pages' => [
                    ['title' => 'Animal', 'index' => 1, 'pageimage' => 'Animal.jpg'],
                ]],
            ]),
            'https://commons.wikimedia.org/w/api.php*' => Http::response([
                'query' => ['pages' => [[
                    'imageinfo' => [[
                        'mime' => 'image/jpeg',
                        'width' => 1200,
                        'height' => 800,
                        'url' => 'https://upload.wikimedia.org/wikipedia/commons/animal.jpg',
                        'descriptionurl' => 'https://commons.wikimedia.org/wiki/File:Animal.jpg',
                        'extmetadata' => [
                            'LicenseShortName' => ['value' => 'CC BY-NC 4.0'],
                        ],
                    ]],
                ]]],
            ]),
        ]);

        $this->actingAs($user)
            ->get(route('animals.image.candidate', $animal))
            ->assertOk()
            ->assertSee('No freely licensed photo passed the checks');
    }

    public function test_image_review_routes_require_authentication_and_update_the_review_time(): void
    {
        Storage::fake('public');
        $animal = Animal::factory()->create(['image_path' => 'animals/test.webp']);

        $this->get(route('animals.images.review'))->assertRedirect(route('login'));

        $user = User::factory()->create();

        $this->actingAs($user)->get(route('animals.images.review'))->assertOk();
        $this->actingAs($user)->patch(route('animals.image.review', $animal), ['reviewed' => true])->assertRedirect();
        $this->assertNotNull($animal->fresh()->image_reviewed_at);

        $this->actingAs($user)->patch(route('animals.image.review', $animal), ['reviewed' => false])->assertRedirect();
        $this->assertNull($animal->fresh()->image_reviewed_at);
    }

    public function test_expired_or_tampered_candidate_tokens_are_rejected(): void
    {
        Storage::fake('public');
        $animal = Animal::factory()->create();
        $user = User::factory()->create();

        $token = Crypt::encryptString((string) json_encode([
            'animal_id' => $animal->id,
            'download_url' => 'https://upload.wikimedia.org/wikipedia/commons/test.jpg',
            'source_url' => 'https://commons.wikimedia.org/wiki/File:Test.jpg',
            'expires_at' => now()->subMinute()->timestamp,
        ]));

        $this->actingAs($user)->post(route('animals.image.from-wikipedia', $animal), [
            'candidate_token' => $token,
        ])->assertSessionHasErrors('image');

        $this->assertNull($animal->fresh()->image_path);
        Http::assertNothingSent();
    }

    private function jpegBytes(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        $color = imagecolorallocate($image, 40, 120, 70);
        imagefill($image, 0, 0, $color);

        ob_start();
        imagejpeg($image, null, 85);
        $contents = (string) ob_get_clean();
        imagedestroy($image);

        return $contents;
    }
}
