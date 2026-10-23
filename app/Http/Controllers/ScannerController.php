<?php

namespace App\Http\Controllers;

use App\Models\Animal;
use App\Models\AnimalVariety;
use App\Models\ConservationAction;
use App\Models\Scan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ScannerController extends Controller
{
    public const MIN_CONFIDENCE_COCO = 0.60;

    public const MIN_CONFIDENCE_MOBILENET = 0.40;

    public const MIN_CONFIDENCE_CLIP = 0.50;

    public const MIN_CONFIDENCE_USER_CONFIRMED = 0.30;

    public function show(): View
    {
        $coco = Animal::query()
            ->scannable()
            ->where('model_source', 'coco-ssd')
            ->orderBy('common_name')
            ->get();

        $mobilenet = Animal::query()
            ->scannable()
            ->where('model_source', 'mobilenet')
            ->orderBy('common_name')
            ->get();

        $labels = Animal::query()
            ->scannable()
            ->orderBy('common_name')
            ->pluck('common_name');

        return view('scanner.index', compact('coco', 'mobilenet', 'labels'));
    }

    /** Auth JSON list of supported scan labels, grouped by detection model. */
    public function labels(): JsonResponse
    {
        // Plain arrays only: Collection objects do not survive the cache
        // round-trip and would serialize as __PHP_Incomplete_Class in JSON.
        $version = $this->libraryVersion();
        $key = 'scanner.supported-labels.v6';

        $data = Cache::get($key);

        if (! is_array($data) || ($data['library_version'] ?? null) !== $version) {
            // The library changed (an animal was added, reviewed or given a
            // variety), so the cached label list no longer describes it.
            Cache::forget($key);
            $data = $this->buildLabels($version);
            Cache::put($key, $data, now()->addDay());
        }

        return response()->json($data);
    }

    /**
     * The current state of the library, used to invalidate the label cache.
     */
    private function libraryVersion(): string
    {
        $animals = Animal::query()->scannable();

        return sha1(implode('|', [
            (clone $animals)->count(),
            (string) (clone $animals)->max('updated_at'),
            AnimalVariety::query()->count(),
            ConservationAction::query()->count(),
        ]));
    }

    /**
     * Build the label payload the browser needs to recognise animals.
     *
     * @return array<string, mixed>
     */
    private function buildLabels(string $version): array
    {
        $animals = Animal::query()
            ->scannable()
            ->with('varieties')
            ->get(['id', 'coco_label', 'common_name', 'scientific_name', 'model_source', 'model_labels', 'clip_names']);

        $coco = $animals
            ->where('model_source', 'coco-ssd')
            ->pluck('coco_label')
            ->sort()
            ->values()
            ->all();

        $mobilenetAnimals = $animals->where('model_source', 'mobilenet');

        $mobilenet = collect()
            ->merge($mobilenetAnimals->pluck('model_labels'))
            ->flatten()
            ->unique()
            ->sort()
            ->values()
            ->all();

        // ImageNet class (and every comma-separated synonym) -> coco_label,
        // lower-cased, so the browser can map a MobileNet className to a
        // library animal without duplicating the data client-side.
        $mobilenetMap = [];
        foreach ($mobilenetAnimals as $animal) {
            foreach ((array) $animal->model_labels as $class) {
                $keys = [strtolower($class)];
                foreach (explode(',', $class) as $synonym) {
                    $keys[] = strtolower(trim($synonym));
                }
                foreach ($keys as $key) {
                    if ($key !== '') {
                        $mobilenetMap[$key] = $animal->coco_label;
                    }
                }
            }
        }
        ksort($mobilenetMap);

        // coco_label -> friendly display name, for scanner status messages.
        $labelNames = $animals
            ->pluck('common_name', 'coco_label')
            ->all();
        ksort($labelNames);

        // The library animals with their scientific names, so the browser can
        // match a species it recognised against the animal it belongs to.
        $library = $animals
            ->map(fn (Animal $animal) => [
                'id' => $animal->id,
                'coco_label' => $animal->coco_label,
                'common_name' => $animal->common_name,
                'scientific_name' => $animal->scientific_name,
            ])
            ->values()
            ->all();

        // Prompts used to build the committed CLIP text embeddings. The
        // browser compares these with clip-text-embeddings.json and only
        // loads the text encoder when they differ.
        $clipPrompts = ['animals' => [], 'varieties' => []];
        $varieties = [];
        foreach ($animals as $animal) {
            $animalPrompts = $animal->clip_names ?: [$animal->common_name];
            $clipPrompts['animals'][$animal->coco_label] = array_values($animalPrompts);

            foreach ($animal->varieties as $variety) {
                $varietyPrompts = $variety->clip_names ?: [$variety->label];
                $clipPrompts['varieties'][$animal->coco_label.':'.$variety->label] = array_values($varietyPrompts);
                $varieties[$animal->coco_label][] = [
                    'id' => $variety->id,
                    'label' => $variety->label,
                    'clip_names' => array_values($varietyPrompts),
                ];
            }
        }
        ksort($clipPrompts['animals']);
        ksort($clipPrompts['varieties']);
        ksort($varieties);

        return [
            'version' => 6,
            'library_version' => $version,
            'coco' => $coco,
            'mobilenet' => $mobilenet,
            'mobilenet_map' => $mobilenetMap,
            'label_names' => $labelNames,
            'library' => $library,
            'clip_prompts' => $clipPrompts,
            'varieties' => $varieties,
            'min_confidences' => [
                'coco-ssd' => self::MIN_CONFIDENCE_COCO,
                'mobilenet' => self::MIN_CONFIDENCE_MOBILENET,
                'clip' => self::MIN_CONFIDENCE_CLIP,
            ],
            'generated_at' => now()->toIso8601String(),
        ];
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'detected_label' => ['required', 'string', 'max:150'],
            'model_source' => ['required', Rule::in(['coco-ssd', 'mobilenet', 'clip'])],
            'confidence' => ['required', 'numeric', 'between:0,1'],
            'top2_label' => ['nullable', 'string', 'max:150'],
            'top2_confidence' => ['nullable', 'numeric', 'between:0,1'],
            'user_confirmed' => ['nullable', 'boolean'],
            'variety_label' => ['nullable', 'string', 'max:150'],
            'variety_confidence' => ['nullable', 'numeric', 'between:0,1'],
            'variety_confirmed' => ['nullable', 'boolean'],
            'response_time_ms' => ['required', 'integer', 'min:1', 'max:60000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $userConfirmed = (bool) ($validated['user_confirmed'] ?? false);

        $minConfidence = match ($validated['model_source']) {
            'mobilenet' => self::MIN_CONFIDENCE_MOBILENET,
            'clip' => $userConfirmed ? self::MIN_CONFIDENCE_USER_CONFIRMED : self::MIN_CONFIDENCE_CLIP,
            default => self::MIN_CONFIDENCE_COCO,
        };

        $animal = $this->findAnimalByLabel($validated['detected_label']);

        if ($validated['confidence'] < $minConfidence) {
            return response()->json([
                'message' => sprintf(
                    'Confidence %.2f is below the %s threshold of %.2f.',
                    $validated['confidence'],
                    strtoupper($validated['model_source']),
                    $minConfidence,
                ),
            ], 422);
        }

        if (! $animal) {
            return response()->json([
                'message' => 'The detected class is not part of EcoSight land-animal coverage.',
            ], 422);
        }

        $variety = null;
        if (! empty($validated['variety_label'])) {
            $variety = $animal->varieties()->where('label', $validated['variety_label'])->first();

            if (! $variety) {
                return response()->json([
                    'message' => sprintf(
                        '%s is not a known variety of %s.',
                        $validated['variety_label'],
                        $animal->common_name,
                    ),
                ], 422);
            }
        }

        $scan = Scan::create([
            'user_id' => $request->user()->id,
            'animal_id' => $animal->id,
            'detected_label' => $validated['detected_label'],
            'model_source' => $validated['model_source'],
            'top2_label' => $validated['top2_label'] ?? null,
            'top2_confidence' => $validated['top2_confidence'] ?? null,
            'user_confirmed' => $userConfirmed,
            'variety_id' => $variety?->id,
            'variety_label' => $variety?->label,
            'variety_confidence' => $variety ? ($validated['variety_confidence'] ?? null) : null,
            'variety_confirmed' => (bool) ($validated['variety_confirmed'] ?? false),
            'confidence' => $validated['confidence'],
            'response_time_ms' => $validated['response_time_ms'],
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'message' => 'Scan saved successfully.',
            'scan_id' => $scan->id,
            'animal' => $animal,
            'variety' => $variety,
            'conservation_actions' => $this->actionsFor($animal),
            'completed_action_ids' => $this->completedActionIds($request, $animal),
        ], 201);
    }

    public function lookup(Request $request, string $label): JsonResponse
    {
        $animal = Animal::with('conservationActions')
            ->scannable()
            ->where(fn ($query) => $this->labelMatches($query, $label))
            ->first();

        if (! $animal) {
            return response()->json(['message' => 'No EcoSight animal record was found.'], 404);
        }

        return response()->json([
            'animal' => $animal,
            'conservation_actions' => $this->actionsFor($animal),
            'completed_action_ids' => $this->completedActionIds($request, $animal),
        ]);
    }

    private function findAnimalByLabel(string $label): ?Animal
    {
        return Animal::with('conservationActions')
            ->scannable()
            ->where(fn ($query) => $this->labelMatches($query, $label))
            ->first();
    }

    private function labelMatches($query, string $label): void
    {
        $query->where('coco_label', $label)
            ->orWhereJsonContains('model_labels', $label);
    }

    private function completedActionIds(Request $request, Animal $animal): array
    {
        return $request->user()
            ->actionCompletions()
            ->whereIn('conservation_action_id', $this->actionsFor($animal)->pluck('id'))
            ->pluck('conservation_action_id')
            ->all();
    }

    /**
     * The conservation actions that apply to an animal: its own, plus the
     * shared general actions of its group.
     *
     * @return Collection<int, ConservationAction>
     */
    private function actionsFor(Animal $animal)
    {
        return $animal->conservationActions->concat($animal->generalActions())->values();
    }
}
