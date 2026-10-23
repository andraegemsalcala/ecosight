<?php

namespace App\Http\Controllers;

use App\Services\WikipediaImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Species lookup for the open-vocabulary scanner.
 *
 * When the scanner recognises a species that is not in the EcoSight library
 * yet, the browser asks this endpoint for the public information that belongs
 * with it: the English Wikipedia summary and the Wikidata statements.
 *
 * Only two public endpoints are used:
 *   - the English Wikipedia REST summary API, and
 *   - the Wikidata EntityData API.
 *
 * Both are cached for 30 days so repeated scans of the same species do not
 * hammer Wikimedia. Wikipedia text is CC BY-SA 4.0, so the response always
 * carries the source URL and the licence for the attribution the UI shows.
 */
class SpeciesLookupController extends Controller
{
    /** How many parent-taxon levels are followed when guessing the group. */
    private const MAX_ANCESTRY_DEPTH = 6;

    public function show(Request $request, string $qid, WikipediaImageService $images): JsonResponse
    {
        $qid = strtoupper(trim($qid));

        if (! preg_match('/^Q\d+$/', $qid)) {
            return response()->json(['message' => 'That is not a valid Wikidata identifier.'], 422);
        }

        if (! $this->knownQid($qid)) {
            return response()->json([
                'message' => 'This species is not in the EcoSight species list.',
            ], 404);
        }

        $species = $this->lookup($qid);

        if (! $species) {
            return response()->json([
                'message' => 'No Wikipedia or Wikidata entry was found for this identifier.',
            ], 404);
        }

        $candidate = null;

        try {
            $candidate = $images->candidateForSpecies(
                $qid,
                isset($species['wikipedia_title']) ? (string) $species['wikipedia_title'] : null,
                (string) ($species['common_name'] ?? ''),
                isset($species['image_url']) ? (string) $species['image_url'] : null,
            );
        } catch (RuntimeException) {
            // The species can still be added without a photo.
        }

        unset($species['image_url']);
        $species['image_candidate'] = $candidate;

        return response()->json($species);
    }

    /**
     * Fetch (and cache) the public information for a Wikidata identifier.
     *
     * Public so the animal controller can reuse the same cached lookups when
     * a user adds a species that the scanner recognised.
     *
     * @return array<string, mixed>|null
     */
    public function lookup(string $qid): ?array
    {
        if (! $this->knownQid($qid)) {
            return null;
        }

        $days = (int) config('ecosight.wikimedia.cache_days', 30);
        $key = 'ecosight.species.'.$qid;

        $cached = Cache::get($key);

        if (is_array($cached)) {
            return $cached;
        }

        $entity = $this->entity($qid);

        if (! $entity) {
            return null;
        }

        $summary = $this->wikipediaSummary($entity);

        $description = $this->summaryText($summary['extract'] ?? null);
        $scientificName = $this->firstStringClaim($entity, 'P225');
        $commonName = $this->englishLabel($entity);

        if ($commonName === null || $commonName === $scientificName) {
            $commonName = $summary['title'] ?? $commonName;
        }

        $species = [
            'qid' => $qid,
            'common_name' => $commonName,
            'scientific_name' => $scientificName,
            'short_description' => $this->englishDescription($entity),
            'description' => $description,
            'description_note' => $description === null
                ? 'No Wikipedia summary found. Please write the description.'
                : null,
            'habitat' => $this->habitat($entity),
            'climate_impact' => null,
            'image_url' => $summary['image_url'] ?? null,
            'conservation_status' => $this->conservationStatus($entity),
            'group' => $this->vocabularyEntry($qid)['group'] ?? $this->group($qid, $entity),
            'group_name' => $this->vocabularyEntry($qid)['group_name'] ?? null,
            'wikipedia_title' => $summary['title'] ?? null,
            'source_url' => $summary['source_url'] ?? ('https://www.wikidata.org/wiki/'.$qid),
            'source_note' => config('ecosight.wikimedia.license', 'Text from Wikipedia, CC BY-SA 4.0'),
            'license' => 'CC BY-SA 4.0',
        ];

        $species['group_label'] = config('ecosight.scope.groups.'.($species['group'] ?? '').'.label');
        $species['wikipedia_url'] = $species['source_url'];

        Cache::put($key, $species, now()->addDays($days));

        return $species;
    }

    /**
     * Read the Wikidata entity, following the redirect for entity ids.
     *
     * @return array<string, mixed>|null
     */
    private function entity(string $qid): ?array
    {
        $response = $this->request('https://www.wikidata.org/wiki/Special:EntityData/'.$qid.'.json');

        if (! is_array($response)) {
            return null;
        }

        $entities = $response['entities'] ?? [];

        return $entities[$qid] ?? (reset($entities) ?: null);
    }

    /**
     * Read the English Wikipedia summary for the entity's English sitelink.
     *
     * @param  array<string, mixed>  $entity
     * @return array{extract?: string, image_url?: string, source_url?: string}|null
     */
    private function wikipediaSummary(array $entity): ?array
    {
        $title = $entity['sitelinks']['enwiki']['title'] ?? null;

        if (! $title) {
            return null;
        }

        $response = $this->request(
            'https://en.wikipedia.org/api/rest_v1/page/summary/'.rawurlencode(str_replace(' ', '_', $title))
        );

        if (! is_array($response)) {
            return null;
        }

        $image = $response['originalimage']['source'] ?? $response['thumbnail']['source'] ?? null;

        return [
            'title' => $title,
            'extract' => $response['extract'] ?? null,
            'image_url' => $image ? $this->cleanUrl($image) : null,
            'source_url' => $response['content_urls']['desktop']['page'] ?? ('https://en.wikipedia.org/wiki/'.rawurlencode(str_replace(' ', '_', $title))),
        ];
    }

    /**
     * Guess the animal group by walking up the parent-taxon chain.
     *
     * @param  array<string, mixed>  $entity
     */
    private function group(string $qid, array $entity): ?string
    {
        $groups = config('ecosight.scope.groups', []);
        $byQid = [];

        foreach ($groups as $key => $group) {
            if (! empty($group['enabled']) && ! empty($group['qid'])) {
                $byQid[$group['qid']] = $key;
            }
        }

        if (isset($byQid[$qid])) {
            return $byQid[$qid];
        }

        $visited = [$qid => true];
        $current = $entity;
        $depth = 0;

        while ($depth < self::MAX_ANCESTRY_DEPTH) {
            $parentQid = $this->firstEntityClaim($current, 'P171');

            if (! $parentQid || isset($visited[$parentQid])) {
                break;
            }

            $visited[$parentQid] = true;

            if (isset($byQid[$parentQid])) {
                return $byQid[$parentQid];
            }

            $parent = $this->entity($parentQid);

            if (! $parent) {
                break;
            }

            $current = $parent;
            $depth++;
        }

        return null;
    }

    /**
     * The IUCN status label for the entity, when it is one we know.
     *
     * @param  array<string, mixed>  $entity
     */
    private function conservationStatus(array $entity): string
    {
        $statusQid = $this->firstEntityClaim($entity, 'P141');
        $statuses = config('ecosight.scope.iucn_statuses', []);

        if ($statusQid && isset($statuses[$statusQid])) {
            return $statuses[$statusQid];
        }

        return config('ecosight.scope.unknown_iucn_status', 'Check IUCN Red List');
    }

    /**
     * Whether the identifier belongs to the server's tier-1 or tier-2
     * vocabulary. This stops the browser from turning any Wikidata item into
     * an EcoSight animal, or from being used as an arbitrary proxy lookup.
     */
    private function knownQid(string $qid): bool
    {
        return isset($this->vocabularyEntries()[$qid]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function vocabularyEntries(): array
    {
        return Cache::remember('ecosight.vocabulary.entries', now()->addDay(), function (): array {
            $known = [];

            foreach ([
                config('ecosight.open_vocabulary.tier1_metadata_path'),
                config('ecosight.open_vocabulary.extended_metadata_path'),
            ] as $relativePath) {
                if (! is_string($relativePath)) {
                    continue;
                }

                $path = base_path($relativePath);

                if (! is_file($path)) {
                    continue;
                }

                $data = json_decode((string) file_get_contents($path), true);

                if (! is_array($data)) {
                    continue;
                }

                foreach (($data['entries'] ?? []) as $entry) {
                    if (isset($entry['qid'])) {
                        $known[(string) $entry['qid']] ??= $entry;
                    }
                }
            }

            return $known;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function vocabularyEntry(string $qid): array
    {
        return $this->vocabularyEntries()[$qid] ?? [];
    }

    /**
     * The first three sentences of a Wikipedia summary, as plain text.
     * Wikipedia is a source, not a template, so the wording is not rewritten.
     */
    private function summaryText(?string $extract): ?string
    {
        if ($extract === null) {
            return null;
        }

        $text = trim(preg_replace('/\s+/', ' ', strip_tags(html_entity_decode($extract, ENT_QUOTES | ENT_HTML5, 'UTF-8'))) ?? '');

        if ($text === '') {
            return null;
        }

        preg_match_all('/[^.!?]+[.!?]+(?:\s|$)/u', $text, $matches);
        $sentences = array_map('trim', array_slice($matches[0] ?? [], 0, 3));
        $summary = implode(' ', $sentences);

        if ($summary === '') {
            $summary = $text;
        }

        return mb_substr($summary, 0, 1200);
    }

    /**
     * The habitat property is an item, so resolve its English label.
     *
     * @param  array<string, mixed>  $entity
     */
    private function habitat(array $entity): ?string
    {
        $habitatQid = $this->firstEntityClaim($entity, 'P2974');

        if (! $habitatQid) {
            return null;
        }

        $habitat = $this->entity($habitatQid);

        return $habitat ? $this->englishLabel($habitat) : null;
    }

    /**
     * @param  array<string, mixed>  $entity
     */
    private function englishLabel(array $entity): ?string
    {
        $label = $entity['labels']['en']['value'] ?? null;

        return is_string($label) && $label !== '' ? $label : null;
    }

    /**
     * @param  array<string, mixed>  $entity
     */
    private function englishDescription(array $entity): ?string
    {
        $description = $entity['descriptions']['en']['value'] ?? null;

        return is_string($description) && $description !== '' ? $description : null;
    }

    /**
     * The first string value of a Wikidata property.
     *
     * @param  array<string, mixed>  $entity
     */
    private function firstStringClaim(array $entity, string $property): ?string
    {
        $value = $entity['claims'][$property][0]['mainsnak']['datavalue']['value'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * The first entity id of a Wikidata property.
     *
     * @param  array<string, mixed>  $entity
     */
    private function firstEntityClaim(array $entity, string $property): ?string
    {
        $value = $entity['claims'][$property][0]['mainsnak']['datavalue']['value']['id'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Perform a cached GET request against a public API.
     *
     * @return array<string, mixed>|null
     */
    private function request(string $url): ?array
    {
        $days = (int) config('ecosight.wikimedia.cache_days', 30);
        $key = 'ecosight.wikimedia.request.'.sha1($url);

        $cached = Cache::get($key);

        if (is_array($cached)) {
            return $cached;
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => config('ecosight.wikimedia.user_agent'),
                'Accept' => 'application/json',
            ])
                ->timeout((int) config('ecosight.wikimedia.timeout_seconds', 5))
                ->get($url);
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();

        if (! is_array($data)) {
            return null;
        }

        Cache::put($key, $data, now()->addDays($days));

        return $data;
    }

    /**
     * Drop the tracking query string Wikimedia adds to image URLs.
     */
    private function cleanUrl(string $url): string
    {
        return strtok($url, '?') ?: $url;
    }
}
