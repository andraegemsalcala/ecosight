<?php

namespace App\Http\Controllers;

use App\Models\Scan;
use App\Models\ScanFeedback;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScanFeedbackController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'scan_id' => ['required', 'integer', 'exists:scans,id'],
            'is_correct' => ['required', 'boolean'],
            'actual_label' => [
                'nullable',
                'string',
                'max:150',
            ],
        ]);

        $scan = Scan::findOrFail($validated['scan_id']);
        abort_unless($scan->user_id === $request->user()->id, 403);

        ScanFeedback::updateOrCreate(
            ['scan_id' => $scan->id],
            [
                'user_id' => $request->user()->id,
                'is_correct' => $validated['is_correct'],
                'actual_label' => $validated['actual_label'] ?? null,
            ]
        );

        return response()->json(['message' => 'Feedback recorded.'], 201);
    }
}
