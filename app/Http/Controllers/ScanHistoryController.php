<?php

namespace App\Http\Controllers;

use App\Models\Scan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ScanHistoryController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $from = $validated['from'] ?? null;
        $to = $validated['to'] ?? null;

        $scans = auth()->user()
            ->scans()
            ->with(['animal', 'variety'])
            ->when($from, fn ($query) => $query->whereDate('created_at', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('created_at', '<=', $to))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('scans.index', compact('scans', 'from', 'to'));
    }

    public function destroy(Scan $scan): RedirectResponse
    {
        abort_unless($scan->user_id === auth()->id(), 403);
        $scan->delete();

        return back()->with('success', 'Scan removed from your history.');
    }

    public function export(Request $request)
    {
        $scans = auth()->user()
            ->scans()
            ->with(['animal', 'variety'])
            ->latest()
            ->get();

        return response()->streamDownload(function () use ($scans) {
            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($output, [
                'Animal',
                'Detected label',
                'Model',
                'Confidence',
                'Runner-up',
                'Runner-up confidence',
                'Variety',
                'Variety confidence',
                'Variety confirmed',
                'User confirmed',
                'Response (ms)',
                'Notes',
                'Date',
            ]);
            foreach ($scans as $scan) {
                fputcsv($output, [
                    $scan->animal?->common_name ?? '',
                    $scan->detected_label,
                    $scan->model_source ?? 'coco-ssd',
                    $scan->confidence,
                    $scan->top2_label ?? '',
                    $scan->top2_confidence ?? '',
                    $scan->variety_label ?? '',
                    $scan->variety_confidence ?? '',
                    $scan->variety_confirmed ? 'yes' : 'no',
                    $scan->user_confirmed ? 'yes' : 'no',
                    $scan->response_time_ms,
                    $scan->notes ?? '',
                    $scan->created_at->toDateTimeString(),
                ]);
            }
            fclose($output);
        }, 'ecosight-my-scans.csv', ['Content-Type' => 'text/csv']);
    }
}
