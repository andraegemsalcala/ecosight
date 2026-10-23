<?php

namespace App\Http\Controllers;

use App\Models\ScannerEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ScannerEventController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in([
                'model-loaded',
                'camera-started',
                'detection-failed',
                'low-confidence',
                'record-missing',
                'species-suggested',
                'species-added',
                'extended-list-loaded',
                'open-vocab-run',
                'open-vocab-candidate-picked',
                'extended-offered',
                'extended-downloaded',
                'extended-declined',
                'extended-candidate-picked',
                'extended-none-of-these',
                'out-of-scope-animal',
                'animal-added-from-scan',
                'lookup-failed',
            ])],
            'reason' => ['nullable', 'string', 'max:100'],
            'response_time_ms' => ['nullable', 'integer', 'min:0', 'max:60000'],
            'model_load_ms' => ['nullable', 'integer', 'min:0', 'max:600000'],
            'camera_start_ms' => ['nullable', 'integer', 'min:0', 'max:120000'],
        ]);

        ScannerEvent::create([
            'user_id' => $request->user()->id,
            'type' => $validated['type'],
            'reason' => $validated['reason'] ?? null,
            'response_time_ms' => $validated['response_time_ms'] ?? null,
            'model_load_ms' => $validated['model_load_ms'] ?? null,
            'camera_start_ms' => $validated['camera_start_ms'] ?? null,
        ]);

        return response()->json(['message' => 'Event recorded.'], 201);
    }
}
