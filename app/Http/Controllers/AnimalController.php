<?php

namespace App\Http\Controllers;

use App\Enums\AnimalClass;
use App\Enums\AnimalKind;
use App\Enums\AnimalStatusCategory;
use App\Http\Requests\AnimalFromScanRequest;
use App\Http\Requests\AnimalRequest;
use App\Http\Requests\AnimalReviewRequest;
use App\Http\Requests\AnimalVarietyRequest;
use App\Models\Animal;
use App\Models\ConservationAction;
use App\Services\AnimalImageService;
use App\Services\WikipediaImageService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;

class AnimalController extends Controller
{
    public function index(Request $request): View
    {
        $q = $request->input('q');
        $legacySearch = $request->input('search');
        $search = is_string($q) ? trim($q) : '';

        if ($search === '') {
            $search = is_string($legacySearch) ? trim($legacySearch) : '';
        }

        $legacyStatusInput = $request->input('status');
        $legacyStatus = is_string($legacyStatusInput) ? trim($legacyStatusInput) : '';
        $classes = $this->allowedFilterValues($request, 'class', AnimalClass::values());
        $statuses = $this->allowedFilterValues($request, 'status', AnimalStatusCategory::values());

        if ($statuses === [] && $legacyStatus !== '') {
            $isKnownStatus = Animal::query()
                ->where('conservation_status', $legacyStatus)
                ->exists();

            if ($isKnownStatus) {
                $statuses = [Animal::statusCategoryFromText($legacyStatus)];
            }
        }

        $filters = [
            'q' => $search,
            'classes' => $classes,
            'statuses' => $statuses,
            'kind' => $this->allowedFilterValue($request, 'kind', AnimalKind::values()),
            'scan' => $this->allowedFilterValue($request, 'scan', ['scannable', 'library-only']),
            'types' => $this->allowedFilterValue($request, 'types', ['with', 'without']),
            'image' => $this->allowedFilterValue($request, 'image', ['with', 'without']),
            'source' => $this->allowedFilterValue($request, 'source', ['seeded', 'user-added']),
            'review' => $this->allowedFilterValue($request, 'review', ['reviewed', 'unreviewed']),
            'sort' => $this->allowedFilterValue($request, 'sort', ['name', 'newest', 'status']) ?? 'name',
        ];

        $animals = $this->applyAnimalFilters(Animal::query(), $filters)
            ->when($filters['sort'] === 'newest', fn ($query) => $query->orderByDesc('created_at')->orderByDesc('id'))
            ->when($filters['sort'] === 'status', fn ($query) => $this->orderByStatus($query))
            ->when($filters['sort'] === 'name', fn ($query) => $query->orderBy('common_name')->orderBy('id'))
            ->paginate(12)
            ->withQueryString();

        $classCounts = $this->applyAnimalFilters(Animal::query(), $filters, 'class')
            ->whereNotNull('animal_class')
            ->selectRaw('animal_class, COUNT(*) as aggregate')
            ->groupBy('animal_class')
            ->pluck('aggregate', 'animal_class');

        $statusCounts = $this->applyAnimalFilters(Animal::query(), $filters, 'status')
            ->selectRaw("COALESCE(status_category, 'unknown') as category, COUNT(*) as aggregate")
            ->groupBy('category')
            ->pluck('aggregate', 'category');

        $classFacets = collect(AnimalClass::options())
            ->map(fn (string $label, string $value): array => [
                'value' => $value,
                'label' => $label,
                'count' => (int) ($classCounts[$value] ?? 0),
            ])
            ->values();

        $statusFacets = collect(AnimalStatusCategory::options())
            ->map(fn (string $label, string $value): array => [
                'value' => $value,
                'label' => $label,
                'count' => (int) ($statusCounts[$value] ?? 0),
            ])
            ->values();

        $conservationStatuses = Animal::query()
            ->distinct()
            ->orderBy('conservation_status')
            ->pluck('conservation_status');

        $activeFilters = $this->activeFilters($request, $filters);
        $status = $legacyStatus;

        return view('animals.index', compact(
            'animals',
            'search',
            'status',
            'conservationStatuses',
            'filters',
            'classFacets',
            'statusFacets',
            'activeFilters',
            'legacyStatus',
        ));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyAnimalFilters(Builder $query, array $filters, ?string $exclude = null): Builder
    {
        if ($exclude !== 'q' && $filters['q'] !== '') {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']).'%';

            $query->where(function ($inner) use ($pattern) {
                $inner->whereRaw("common_name LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("scientific_name LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("coco_label LIKE ? ESCAPE '!'", [$pattern]);
            });
        }

        if ($exclude !== 'class' && $filters['classes'] !== []) {
            $query->whereIn('animal_class', $filters['classes']);
        }

        if ($exclude !== 'status' && $filters['statuses'] !== []) {
            $query->whereIn('status_category', $filters['statuses']);
        }

        if ($exclude !== 'kind' && $filters['kind'] !== null) {
            $query->where('is_domestic', $filters['kind'] === AnimalKind::Domestic->value);
        }

        if ($exclude !== 'scan' && $filters['scan'] !== null) {
            $query->where('is_scannable', $filters['scan'] === 'scannable');
        }

        if ($exclude !== 'types' && $filters['types'] !== null) {
            $filters['types'] === 'with'
                ? $query->whereHas('varieties')
                : $query->whereDoesntHave('varieties');
        }

        if ($exclude !== 'image' && $filters['image'] !== null) {
            $filters['image'] === 'with' ? $query->withImage() : $query->withoutImage();
        }

        if ($exclude !== 'source' && $filters['source'] !== null) {
            $query->where('origin', $filters['source']);
        }

        if ($exclude !== 'review' && $filters['review'] !== null) {
            $query->where('is_reviewed', $filters['review'] === 'reviewed');
        }

        return $query;
    }

    private function orderByStatus(Builder $query): void
    {
        $cases = collect(AnimalStatusCategory::cases())
            ->map(fn (AnimalStatusCategory $status): string => "WHEN '{$status->value}' THEN {$status->sortRank()}")
            ->implode(' ');

        $query->orderByRaw("CASE status_category {$cases} ELSE 999 END")
            ->orderBy('common_name')
            ->orderBy('id');
    }

    /**
     * @param  array<int, string>  $allowed
     * @return array<int, string>
     */
    private function allowedFilterValues(Request $request, string $key, array $allowed): array
    {
        $values = $request->input($key, []);

        if (! is_array($values)) {
            $values = [$values];
        }

        return array_values(array_intersect(
            $allowed,
            array_values(array_filter(array_map(
                fn (mixed $value): string => is_scalar($value) ? (string) $value : '',
                $values,
            ))),
        ));
    }

    /**
     * @param  array<int, string>  $allowed
     */
    private function allowedFilterValue(Request $request, string $key, array $allowed): ?string
    {
        $value = $request->input($key);

        return is_string($value) && in_array($value, $allowed, true) ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array{label: string, remove_url: string}>
     */
    private function activeFilters(Request $request, array $filters): array
    {
        $chips = [];

        if ($filters['q'] !== '') {
            $chips[] = ['label' => 'Search: '.$filters['q'], 'remove_url' => $this->removeFilterValues($request, ['q', 'search'])];
        }

        foreach ($filters['classes'] as $class) {
            $chips[] = [
                'label' => AnimalClass::from($class)->label(),
                'remove_url' => $this->removeFilterValues($request, ['class'], $class),
            ];
        }

        foreach ($filters['statuses'] as $status) {
            $chips[] = [
                'label' => AnimalStatusCategory::from($status)->label(),
                'remove_url' => $this->removeFilterValues($request, ['status'], $status),
            ];
        }

        $singleFilters = [
            'kind' => $filters['kind'],
            'scan' => $filters['scan'],
            'types' => $filters['types'],
            'image' => $filters['image'],
            'source' => $filters['source'],
            'review' => $filters['review'],
        ];

        $labels = [
            'kind' => [AnimalKind::Wild->value => 'Wild', AnimalKind::Domestic->value => 'Domestic'],
            'scan' => ['scannable' => 'Scanner-detectable', 'library-only' => 'Library only'],
            'types' => ['with' => 'Has breeds or varieties', 'without' => 'No breeds or varieties'],
            'image' => ['with' => 'Has an image', 'without' => 'No image'],
            'source' => ['seeded' => 'Seeded animal', 'user-added' => 'User-added animal'],
            'review' => ['reviewed' => 'Reviewed', 'unreviewed' => 'Unreviewed'],
        ];

        foreach ($singleFilters as $key => $value) {
            if ($value === null) {
                continue;
            }

            $chips[] = [
                'label' => $labels[$key][$value],
                'remove_url' => $this->removeFilterValues($request, [$key]),
            ];
        }

        return $chips;
    }

    /**
     * @param  array<int, string>  $keys
     */
    private function removeFilterValues(Request $request, array $keys, ?string $value = null): string
    {
        $query = $request->query();
        unset($query['page']);

        foreach ($keys as $key) {
            if ($value === null) {
                unset($query[$key]);

                continue;
            }

            $current = $query[$key] ?? null;

            if (is_array($current)) {
                $remaining = array_values(array_filter(
                    $current,
                    fn (mixed $item): bool => (string) $item !== $value,
                ));

                if ($remaining === []) {
                    unset($query[$key]);
                } else {
                    $query[$key] = $remaining;
                }
            } elseif ((string) $current === $value) {
                unset($query[$key]);
            }
        }

        return route('animals.index', $query);
    }

    public function create(): View
    {
        return view('animals.create', [
            'animal' => new Animal,
            'actions' => collect(),
        ]);
    }

    public function store(AnimalRequest $request): RedirectResponse
    {
        $animal = Animal::create($this->scanData($request->safe()->except('actions')));
        $this->syncActions($animal, $request->validated('actions', []));

        return redirect()->route('animals.show', $animal)->with('success', 'Animal created successfully.');
    }

    public function show(Animal $animal): View
    {
        $animal->load(['conservationActions', 'varieties']);

        $generalActions = $animal->generalActions();
        $actions = $animal->conservationActions->concat($generalActions)->values();

        $completedActionIds = auth()->check()
            ? auth()->user()->actionCompletions()
                ->whereIn('conservation_action_id', $actions->pluck('id'))
                ->pluck('conservation_action_id')
            : collect();

        return view('animals.show', compact('animal', 'completedActionIds', 'actions'));
    }

    public function edit(Animal $animal): View
    {
        $animal->load('conservationActions');

        return view('animals.edit', [
            'animal' => $animal,
            'actions' => $animal->conservationActions,
        ]);
    }

    public function update(AnimalRequest $request, Animal $animal): RedirectResponse
    {
        $animal->update($this->scanData($request->safe()->except('actions')));
        $this->syncActions($animal, $request->validated('actions', []));

        return redirect()->route('animals.show', $animal)->with('success', 'Animal updated successfully.');
    }

    public function destroy(Animal $animal, AnimalImageService $images): RedirectResponse
    {
        $images->remove($animal);
        $animal->delete();

        return redirect()->route('animals.index')->with('success', 'Animal deleted successfully.');
    }

    public function destroyAction(Animal $animal, ConservationAction $action): RedirectResponse
    {
        abort_unless($action->animal_id === $animal->id, 404);
        $action->delete();

        return back()->with('success', 'Conservation action removed.');
    }

    /**
     * Add an animal that the open-vocabulary scanner matched.
     *
     * The species details come from the cached Wikipedia and Wikidata lookup
     * on the server. When the user points at a library animal, the species is
     * added as a type of that animal instead of as a new library entry.
     */
    public function storeFromScan(
        AnimalFromScanRequest $request,
        SpeciesLookupController $speciesLookup,
        WikipediaImageService $wikipedia,
        AnimalImageService $images,
    ): JsonResponse {
        $data = $request->validated();
        $qid = strtoupper($data['qid']);

        $species = $speciesLookup->lookup($qid);

        if (! $species) {
            return response()->json([
                'message' => 'No public information was found for this species yet, so it cannot be added.',
            ], 422);
        }

        $commonName = trim((string) ($data['common_name'] ?? '')) ?: ($species['common_name'] ?? null);
        $scientificName = trim((string) ($data['scientific_name'] ?? '')) ?: ($species['scientific_name'] ?? null);
        $groupName = trim((string) ($data['group_name'] ?? '')) ?: $commonName;

        if (blank($commonName)) {
            return response()->json([
                'message' => 'This species has no name that can be shown.',
            ], 422);
        }

        $description = trim((string) ($data['description'] ?? '')) ?: ($species['description'] ?? null);

        if (blank($description)) {
            return response()->json([
                'message' => 'Please write a short description before adding this species.',
            ], 422);
        }

        if (! empty($data['as_type_of'])) {
            return $this->addAsType(
                Animal::findOrFail($data['as_type_of']),
                $qid,
                $groupName,
                $scientificName,
                $data['kind'] ?? 'species',
            );
        }

        $existing = Animal::where('wikidata_qid', $qid)->first();

        if ($existing) {
            return response()->json([
                'message' => $commonName.' is already in the EcoSight library.',
                'animal_url' => route('animals.show', $existing),
            ], 422);
        }

        $animal = Animal::create([
            'common_name' => $commonName,
            'scientific_name' => $scientificName ?: $commonName,
            'coco_label' => $this->uniqueLabel($groupName, $scientificName),
            'model_source' => 'clip',
            'group' => $species['group'] ?? null,
            'model_labels' => [$commonName],
            'clip_names' => $this->clipNames($commonName, $scientificName),
            'is_scannable' => true,
            'description' => Str::limit((string) $description, 1200, ''),
            'habitat' => $data['habitat'] ?? $species['habitat'] ?? null,
            'climate_impact' => $data['climate_impact'] ?? $species['climate_impact'] ?? null,
            'conservation_status' => filled($data['conservation_status'] ?? null) ? $data['conservation_status'] : ($species['conservation_status'] ?? config('ecosight.scope.unknown_iucn_status', 'Unknown')),
            'wikidata_qid' => $qid,
            'source_url' => $data['source_url'] ?? $species['source_url'] ?? null,
            'source_note' => $species['source_note'] ?? null,
            'origin' => 'user-added',
            'is_reviewed' => false,
            'added_by' => $request->user()->id,
        ]);

        $imageWarning = null;

        if (filled($data['image_candidate_token'] ?? null)) {
            try {
                $candidate = $wikipedia->decodeScanCandidate($data['image_candidate_token'], $qid);
                $contents = $wikipedia->download($candidate);

                $images->storeBinary($contents, $animal, [
                    'alt' => $candidate['alt'] ?? 'Photo of a '.$commonName,
                    'credit' => $candidate['credit'] ?? null,
                    'license' => $candidate['license'] ?? null,
                    'source_url' => $candidate['source_url'] ?? null,
                    'reviewed' => true,
                ]);
            } catch (RuntimeException $exception) {
                $imageWarning = $exception->getMessage();
            }
        }

        $animal->refresh();

        $message = $commonName.' was added to your library. A reviewer can confirm it later.';

        if ($imageWarning !== null) {
            $message .= ' The photo was not added: '.$imageWarning;
        }

        return response()->json([
            'message' => $message,
            'animal' => $this->animalPayload($animal),
        ], 201);
    }

    /**
     * Add a variety to an animal.
     */
    public function storeVariety(AnimalVarietyRequest $request, Animal $animal): JsonResponse
    {
        $data = $request->validated();

        $variety = $animal->varieties()->create([
            'label' => $data['label'],
            'kind' => $data['kind'],
            'clip_names' => $data['clip_names'] ?? $this->clipNames($data['label'], $data['scientific_name'] ?? null),
        ]);

        return response()->json([
            'message' => $variety->label.' was added as a type of '.$animal->common_name.'.',
            'variety' => [
                'id' => $variety->id,
                'label' => $variety->label,
                'kind' => $variety->kind,
            ],
        ], 201);
    }

    /**
     * Mark an animal as reviewed, or send it back for another look.
     */
    public function review(AnimalReviewRequest $request, Animal $animal): JsonResponse|RedirectResponse
    {
        $animal->update([
            'is_reviewed' => $request->validated('is_reviewed'),
        ]);

        $message = $animal->is_reviewed
            ? $animal->common_name.' was marked as reviewed.'
            : $animal->common_name.' was marked as needing a review.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'animal' => $this->animalPayload($animal->fresh()),
            ]);
        }

        return back()->with('status', $message);
    }

    /**
     * @param  array<string, mixed>|null  $clipNames
     */
    private function addAsType(Animal $animal, string $qid, string $groupName, ?string $scientificName, string $kind): JsonResponse
    {
        $existing = $animal->varieties()->where('label', $groupName)->first();

        if ($existing) {
            return response()->json([
                'message' => $groupName.' is already a type of '.$animal->common_name.'.',
                'variety' => [
                    'id' => $existing->id,
                    'label' => $existing->label,
                    'kind' => $existing->kind,
                ],
            ], 422);
        }

        $variety = $animal->varieties()->create([
            'label' => $groupName,
            'kind' => $kind,
            'clip_names' => $this->clipNames($groupName, $scientificName),
        ]);

        return response()->json([
            'message' => $groupName.' was added as a type of '.$animal->common_name.'.',
            'variety' => [
                'id' => $variety->id,
                'label' => $variety->label,
                'kind' => $variety->kind,
            ],
            'animal' => $this->animalPayload($animal->fresh()),
        ], 201);
    }

    /**
     * A URL-safe, unique label built from the general name and the scientific
     * epithet: "Fox" + "Vulpes vulpes" -> "fox-vulpes-vulpes".
     */
    private function uniqueLabel(string $groupName, ?string $scientificName): string
    {
        $base = Str::slug($groupName);
        $scientific = Str::slug((string) $scientificName);

        if ($base === '') {
            $base = 'animal';
        }
        if ($scientific !== '') {
            $base .= '-'.$scientific;
        }

        $label = $base;
        $suffix = 2;

        while (Animal::where('coco_label', $label)->exists()) {
            $label = $base.'-'.$suffix;
            $suffix++;
        }

        return $label;
    }

    /**
     * The prompt variants used to recognise an animal with CLIP.
     *
     * @return array<int, string>
     */
    private function clipNames(string $commonName, ?string $scientificName): array
    {
        $names = [];

        foreach (config('ecosight.open_vocabulary.templates', []) as $template) {
            $names[] = str_replace('{name}', strtolower($commonName), $template);
        }

        if (! blank($scientificName)) {
            $names[] = $scientificName;
        }

        return array_values(array_unique(array_filter($names)));
    }

    /**
     * @return array<string, mixed>
     */
    private function animalPayload(Animal $animal): array
    {
        return [
            'id' => $animal->id,
            'common_name' => $animal->common_name,
            'scientific_name' => $animal->scientific_name,
            'group' => $animal->group,
            'group_label' => $animal->groupLabel(),
            'is_reviewed' => $animal->is_reviewed,
            'origin' => $animal->origin,
            'wikidata_qid' => $animal->wikidata_qid,
            'image_url' => $animal->image_url,
            'conservation_status' => $animal->conservation_status,
            'url' => route('animals.show', $animal),
        ];
    }

    private function scanData(array $data): array
    {
        if (($data['model_source'] ?? 'coco-ssd') === 'coco-ssd' && empty($data['model_labels'])) {
            $data['model_labels'] = [$data['coco_label']];
        }

        return $data;
    }

    private function syncActions(Animal $animal, array $actions): void
    {
        foreach ($actions as $actionData) {
            if (empty($actionData['title'])) {
                continue;
            }

            if (! empty($actionData['id'])) {
                $action = $animal->conservationActions()->find($actionData['id']);
                if ($action) {
                    $action->update([
                        'title' => $actionData['title'],
                        'description' => $actionData['description'],
                        'difficulty' => $actionData['difficulty'],
                        'category' => $actionData['category'],
                    ]);
                }

                continue;
            }

            $animal->conservationActions()->create([
                'title' => $actionData['title'],
                'description' => $actionData['description'],
                'difficulty' => $actionData['difficulty'],
                'category' => $actionData['category'],
            ]);
        }
    }
}
