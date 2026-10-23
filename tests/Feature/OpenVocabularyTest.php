<?php

namespace Tests\Feature;

use App\Models\Animal;
use App\Models\AnimalVariety;
use App\Models\ConservationAction;
use App\Models\User;
use Database\Seeders\AnimalExpansionSeeder;
use Database\Seeders\AnimalPetSeeder;
use Database\Seeders\AnimalSeeder;
use Database\Seeders\ConservationActionExpansionSeeder;
use Database\Seeders\ConservationActionPetSeeder;
use Database\Seeders\GeneralActionsSeeder;
use Database\Seeders\VarietySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OpenVocabularyTest extends TestCase
{
    use RefreshDatabase;

    private const QID = 'Q25334';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->seed([
            AnimalSeeder::class,
            AnimalExpansionSeeder::class,
            AnimalPetSeeder::class,
            ConservationActionExpansionSeeder::class,
            ConservationActionPetSeeder::class,
            GeneralActionsSeeder::class,
            VarietySeeder::class,
        ]);
    }

    /**
     * The public information the species lookup reads from Wikimedia.
     */
    private function fakeWikimedia(): void
    {
        $entity = [
            'entities' => [
                self::QID => [
                    'labels' => ['en' => ['value' => 'European robin']],
                    'descriptions' => ['en' => ['value' => 'species of bird']],
                    'claims' => [
                        'P225' => [['mainsnak' => ['datavalue' => ['value' => 'Erithacus rubecula']]]],
                        'P141' => [['mainsnak' => ['datavalue' => ['value' => ['id' => 'Q211005']]]]],
                        'P171' => [['mainsnak' => ['datavalue' => ['value' => ['id' => 'Q5113']]]]],
                    ],
                    'sitelinks' => ['enwiki' => ['title' => 'European robin']],
                ],
            ],
        ];

        $summary = [
            'extract' => 'The European robin is a small insect-eating bird.',
            'originalimage' => ['source' => 'https://upload.wikimedia.org/robin.jpg?x=1'],
            'content_urls' => ['desktop' => ['page' => 'https://en.wikipedia.org/wiki/European_robin']],
        ];

        Http::fake([
            'https://www.wikidata.org/wiki/Special:EntityData/'.self::QID.'.json' => Http::response($entity, 200),
            'https://en.wikipedia.org/api/rest_v1/page/summary/European_robin' => Http::response($summary, 200),
            '*' => Http::response([], 404),
        ]);
    }

    public function test_species_lookup_returns_the_public_information_of_a_species(): void
    {
        $this->fakeWikimedia();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/scanner/lookup/'.self::QID);

        $response->assertOk()
            ->assertJsonPath('qid', self::QID)
            ->assertJsonPath('common_name', 'European robin')
            ->assertJsonPath('scientific_name', 'Erithacus rubecula')
            ->assertJsonPath('group', 'birds')
            ->assertJsonPath('group_label', 'Birds')
            ->assertJsonPath('conservation_status', 'Least Concern')
            ->assertJsonPath('source_url', 'https://en.wikipedia.org/wiki/European_robin')
            ->assertJsonPath('license', 'CC BY-SA 4.0');

        $this->assertArrayNotHasKey('image_url', $response->json());
        $this->assertNull($response->json('image_candidate'));
    }

    public function test_species_lookup_uses_the_wikipedia_title_when_the_label_is_scientific(): void
    {
        Http::fake([
            'https://www.wikidata.org/wiki/Special:EntityData/Q212398.json' => Http::response([
                'entities' => [
                    'Q212398' => [
                        'labels' => ['en' => ['value' => 'Danaus plexippus']],
                        'claims' => [
                            'P225' => [['mainsnak' => ['datavalue' => ['value' => 'Danaus plexippus']]]],
                        ],
                        'sitelinks' => ['enwiki' => ['title' => 'Monarch butterfly']],
                    ],
                ],
            ], 200),
            'https://en.wikipedia.org/api/rest_v1/page/summary/Monarch_butterfly' => Http::response([
                'extract' => 'The monarch butterfly is a milkweed butterfly.  It migrates each year.',
                'content_urls' => ['desktop' => ['page' => 'https://en.wikipedia.org/wiki/Monarch_butterfly']],
            ], 200),
            '*' => Http::response([], 404),
        ]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/scanner/lookup/Q212398')
            ->assertOk()
            ->assertJsonPath('common_name', 'Monarch butterfly')
            ->assertJsonPath('scientific_name', 'Danaus plexippus')
            ->assertJsonPath('group', 'insects')
            ->assertJsonPath('description', 'The monarch butterfly is a milkweed butterfly. It migrates each year.');
    }

    public function test_species_lookup_rejects_an_invalid_identifier(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/scanner/lookup/not-a-qid')
            ->assertStatus(422)
            ->assertJsonPath('message', 'That is not a valid Wikidata identifier.');
    }

    public function test_species_lookup_reports_a_species_without_public_information(): void
    {
        Http::fake(['*' => Http::response([], 404)]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/scanner/lookup/Q123456')
            ->assertStatus(404);
    }

    public function test_species_lookup_requires_authentication(): void
    {
        $this->getJson('/scanner/lookup/'.self::QID)->assertStatus(401);
    }

    public function test_species_lookup_rejects_an_identifier_outside_the_vocabulary(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/scanner/lookup/Q999999')
            ->assertStatus(404)
            ->assertJsonPath('message', 'This species is not in the EcoSight species list.');
    }

    public function test_a_matched_species_can_be_added_to_the_library(): void
    {
        $this->fakeWikimedia();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/animals/from-scan', [
            'qid' => self::QID,
            'confidence' => 0.42, 'common_name' => 'European robin', 'group_name' => 'Robin',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('animal.common_name', 'European robin')
            ->assertJsonPath('animal.wikidata_qid', self::QID)
            ->assertJsonPath('animal.origin', 'user-added')
            ->assertJsonPath('animal.is_reviewed', false)
            ->assertJsonPath('animal.group', 'birds');

        $this->assertDatabaseHas('animals', [
            'common_name' => 'European robin',
            'scientific_name' => 'Erithacus rubecula',
            'coco_label' => 'robin-erithacus-rubecula',
            'wikidata_qid' => self::QID,
            'origin' => 'user-added',
            'is_reviewed' => false,
            'added_by' => $user->id,
            'model_source' => 'clip',
            'is_scannable' => true,
        ]);

        // The general actions of the group apply to the new animal.
        $this->assertSame(2, ConservationAction::whereNull('animal_id')->where('is_general', true)->where('group', 'birds')->count());
    }

    public function test_a_scanned_image_is_downloaded_only_after_the_species_is_added(): void
    {
        Storage::fake('public');
        $imageBytes = $this->jpegBytes();

        Http::fake([
            'https://www.wikidata.org/wiki/Special:EntityData/'.self::QID.'.json' => Http::response([
                'entities' => [
                    self::QID => [
                        'labels' => ['en' => ['value' => 'European robin']],
                        'descriptions' => ['en' => ['value' => 'species of bird']],
                        'claims' => [
                            'P225' => [['mainsnak' => ['datavalue' => ['value' => 'Erithacus rubecula']]]],
                            'P141' => [['mainsnak' => ['datavalue' => ['value' => ['id' => 'Q211005']]]]],
                            'P171' => [['mainsnak' => ['datavalue' => ['value' => ['id' => 'Q5113']]]]],
                        ],
                        'sitelinks' => ['enwiki' => ['title' => 'European robin']],
                    ],
                ],
            ], 200),
            'https://en.wikipedia.org/api/rest_v1/page/summary/European_robin' => Http::response([
                'title' => 'European robin',
                'extract' => 'The European robin is a small insect-eating bird.',
                'originalimage' => ['source' => 'https://upload.wikimedia.org/wikipedia/commons/a/ab/European_robin.jpg'],
                'content_urls' => ['desktop' => ['page' => 'https://en.wikipedia.org/wiki/European_robin']],
                'thumbnail' => ['source' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/ab/European_robin.jpg/1200px-European_robin.jpg'],
            ], 200),
            'https://commons.wikimedia.org/w/api.php*' => Http::response([
                'query' => ['pages' => [[
                    'imageinfo' => [[
                        'mime' => 'image/jpeg',
                        'width' => 1200,
                        'height' => 800,
                        'thumburl' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/ab/European_robin.jpg/1200px-European_robin.jpg',
                        'url' => 'https://upload.wikimedia.org/wikipedia/commons/a/ab/European_robin.jpg',
                        'descriptionurl' => 'https://commons.wikimedia.org/wiki/File:European_robin.jpg',
                        'extmetadata' => [
                            'LicenseShortName' => ['value' => 'CC BY-SA 4.0'],
                            'Artist' => ['value' => 'Jane Doe'],
                            'ImageDescription' => ['value' => 'A European robin on a branch'],
                        ],
                    ]],
                ]]],
            ]),
            'https://upload.wikimedia.org/*' => Http::response($imageBytes, 200, ['Content-Type' => 'image/jpeg']),
            '*' => Http::response([], 404),
        ]);

        $user = User::factory()->create();

        $lookup = $this->actingAs($user)->getJson('/scanner/lookup/'.self::QID)->assertOk();
        $this->assertArrayNotHasKey('image_url', $lookup->json());

        $token = $lookup->json('image_candidate.token');
        $this->assertIsString($token);
        $this->assertNotSame('', $token);
        $this->assertStringStartsWith('https://upload.wikimedia.org/wikipedia/commons/', $lookup->json('image_candidate.preview_url'));
        $this->assertDatabaseMissing('animals', ['wikidata_qid' => self::QID]);

        $this->actingAs($user)->postJson('/animals/from-scan', [
            'qid' => self::QID,
            'confidence' => 0.42,
            'common_name' => 'European robin',
            'group_name' => 'Robin',
            'image_candidate_token' => $token,
        ])->assertStatus(201);

        $animal = Animal::where('wikidata_qid', self::QID)->firstOrFail();

        $this->assertNotNull($animal->image_path);
        $this->assertNull($animal->image_url);
        $this->assertSame('Photo of a European robin', $animal->image_alt);
        $this->assertSame('Jane Doe', $animal->image_credit);
        $this->assertSame('CC BY-SA 4.0', $animal->image_license);
        $this->assertSame('https://commons.wikimedia.org/wiki/File:European_robin.jpg', $animal->image_source_url);
        $this->assertNotNull($animal->image_reviewed_at);
        Storage::disk('public')->assertExists($animal->image_path);
    }

    public function test_a_matched_species_can_be_added_as_a_type_of_a_library_animal(): void
    {
        $this->fakeWikimedia();
        $user = User::factory()->create();
        $fox = Animal::where('coco_label', 'common_fox')->firstOrFail();

        $response = $this->actingAs($user)->postJson('/animals/from-scan', [
            'qid' => self::QID,
            'confidence' => 0.31, 'common_name' => 'European robin', 'group_name' => 'Robin',
            'as_type_of' => $fox->id,
            'kind' => 'species',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('variety.kind', 'species')
            ->assertJsonPath('variety.label', 'Robin');

        $this->assertDatabaseHas('animal_varieties', [
            'animal_id' => $fox->id,
            'label' => 'Robin',
            'kind' => 'species',
        ]);

        // Nothing was added to the library itself.
        $this->assertDatabaseMissing('animals', ['wikidata_qid' => self::QID]);
    }

    public function test_a_species_cannot_be_added_twice(): void
    {
        $this->fakeWikimedia();
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/animals/from-scan', [
            'qid' => self::QID,
            'confidence' => 0.4, 'common_name' => 'European robin', 'group_name' => 'Robin',
        ])->assertStatus(201);

        $this->actingAs($user)->postJson('/animals/from-scan', [
            'qid' => self::QID,
            'confidence' => 0.4, 'common_name' => 'European robin', 'group_name' => 'Robin',
        ])->assertStatus(422)->assertJsonPath(
            'message',
            'European robin is already in the EcoSight library.',
        );
    }

    public function test_adding_a_species_validates_its_payload(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/animals/from-scan', [
            'qid' => 'not-a-qid',
            'confidence' => 0.4, 'common_name' => 'European robin', 'group_name' => 'Robin',
        ])->assertStatus(422)->assertJsonValidationErrors('qid');

        $this->actingAs($user)->postJson('/animals/from-scan', [
            'qid' => self::QID,
            'confidence' => 4,
        ])->assertStatus(422)->assertJsonValidationErrors('confidence');
    }

    public function test_adding_a_species_requires_a_description(): void
    {
        Http::fake([
            'https://www.wikidata.org/wiki/Special:EntityData/'.self::QID.'.json' => Http::response([
                'entities' => [
                    self::QID => [
                        'labels' => ['en' => ['value' => 'European robin']],
                        'claims' => [
                            'P225' => [['mainsnak' => ['datavalue' => ['value' => 'Erithacus rubecula']]]],
                            'P171' => [['mainsnak' => ['datavalue' => ['value' => ['id' => 'Q5113']]]]],
                        ],
                    ],
                ],
            ], 200),
            '*' => Http::response([], 404),
        ]);
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/animals/from-scan', [
            'qid' => self::QID,
            'confidence' => 0.4,
            'common_name' => 'European robin',
            'group_name' => 'Robin',
        ])->assertStatus(422)->assertJsonPath(
            'message',
            'Please write a short description before adding this species.',
        );
    }

    public function test_adding_a_species_requires_authentication(): void
    {
        $this->postJson('/animals/from-scan', [
            'qid' => self::QID,
            'confidence' => 0.4, 'common_name' => 'European robin', 'group_name' => 'Robin',
        ])->assertStatus(401);
    }

    public function test_a_user_added_animal_can_be_reviewed(): void
    {
        $this->fakeWikimedia();
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/animals/from-scan', [
            'qid' => self::QID,
            'confidence' => 0.4, 'common_name' => 'European robin', 'group_name' => 'Robin',
        ])->assertStatus(201);

        $animal = Animal::where('wikidata_qid', self::QID)->firstOrFail();

        $this->actingAs($user)
            ->patchJson('/animals/'.$animal->id.'/review', ['is_reviewed' => true])
            ->assertOk()
            ->assertJsonPath('animal.is_reviewed', true);

        $this->assertTrue($animal->fresh()->is_reviewed);
    }

    public function test_reviewing_from_the_animal_page_redirects_back(): void
    {
        $this->fakeWikimedia();
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/animals/from-scan', [
            'qid' => self::QID,
            'confidence' => 0.4, 'common_name' => 'European robin', 'group_name' => 'Robin',
        ])->assertStatus(201);

        $animal = Animal::where('wikidata_qid', self::QID)->firstOrFail();

        $this->actingAs($user)
            ->from('/animals/'.$animal->id)
            ->patch('/animals/'.$animal->id.'/review', ['is_reviewed' => true])
            ->assertRedirect('/animals/'.$animal->id)
            ->assertSessionHas('status', 'European robin was marked as reviewed.');

        $this->assertTrue($animal->fresh()->is_reviewed);
    }

    public function test_a_variety_can_be_added_to_an_animal(): void
    {
        $user = User::factory()->create();
        $dog = Animal::where('coco_label', 'dog')->firstOrFail();

        $this->actingAs($user)
            ->postJson('/animals/'.$dog->id.'/varieties', [
                'label' => 'European robin',
                'kind' => 'species',
            ])
            ->assertStatus(201)
            ->assertJsonPath('variety.label', 'European robin')
            ->assertJsonPath('variety.kind', 'species');

        $this->assertDatabaseHas('animal_varieties', [
            'animal_id' => $dog->id,
            'label' => 'European robin',
            'kind' => 'species',
        ]);
    }

    public function test_the_scanner_lookup_shows_the_general_actions_of_a_user_added_animal(): void
    {
        $this->fakeWikimedia();
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/animals/from-scan', [
            'qid' => self::QID,
            'confidence' => 0.4, 'common_name' => 'European robin', 'group_name' => 'Robin',
        ])->assertStatus(201);

        $animal = Animal::where('wikidata_qid', self::QID)->firstOrFail();

        $response = $this->actingAs($user)->getJson('/scanner/animal/robin-erithacus-rubecula');

        $response->assertOk()
            ->assertJsonPath('animal.common_name', 'European robin')
            ->assertJsonCount(2, 'conservation_actions');
    }

    public function test_the_variety_factory_kinds_stay_separated(): void
    {
        $this->assertSame('breed', AnimalVariety::firstOrFail()->kind);
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
