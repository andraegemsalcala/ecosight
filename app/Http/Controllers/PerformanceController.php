<?php

namespace App\Http\Controllers;

use App\Models\Animal;
use App\Models\Scan;
use App\Models\ScanFeedback;
use App\Models\ScannerEvent;
use App\Models\SurveyResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class PerformanceController extends Controller
{
    public function index(Request $request): View
    {
        $responseValues = Scan::query()
            ->orderBy('response_time_ms')
            ->pluck('response_time_ms')
            ->map(fn ($value) => (int) $value)
            ->values();

        $avgResponse = round($responseValues->avg() ?? 0, 1);
        $medianResponse = $this->percentile($responseValues, 50);
        $p95Response = $this->percentile($responseValues, 95);

        $modelLoadAvg = round((float) ScannerEvent::query()
            ->where('type', 'model-loaded')
            ->whereNotNull('model_load_ms')
            ->avg('model_load_ms') ?? 0, 1);

        $cameraStartAvg = round((float) ScannerEvent::query()
            ->where('type', 'camera-started')
            ->whereNotNull('camera_start_ms')
            ->avg('camera_start_ms') ?? 0, 1);

        $correctFeedback = ScanFeedback::where('is_correct', true)->count();
        $incorrectFeedback = ScanFeedback::where('is_correct', false)->count();
        $feedbackTotal = $correctFeedback + $incorrectFeedback;
        $accuracyPercent = $feedbackTotal > 0
            ? round(($correctFeedback / $feedbackTotal) * 100, 1)
            : null;

        $lowConfidenceCount = ScannerEvent::where('type', 'low-confidence')->count();
        $positiveDetections = Scan::count();
        $eventDenominator = $lowConfidenceCount + $positiveDetections;
        $lowConfidenceRate = $eventDenominator > 0
            ? round(($lowConfidenceCount / $eventDenominator) * 100, 1)
            : null;

        $failureCounts = ScannerEvent::query()
            ->where('type', 'detection-failed')
            ->whereNotNull('reason')
            ->selectRaw('reason, COUNT(*) as count')
            ->groupBy('reason')
            ->orderByDesc('count')
            ->get();

        $survey = SurveyResponse::query()->selectRaw(
            'AVG(ease_rating) as ease, AVG(speed_rating) as speed, AVG(clarity_rating) as clarity, AVG(usefulness_rating) as usefulness, COUNT(*) as total'
        )->first();

        $perAnimal = $this->perAnimalStats();

        $openVocabTypes = [
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
        ];

        $openVocabCounts = ScannerEvent::query()
            ->whereIn('type', $openVocabTypes)
            ->selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type');

        return view('performance.index', compact(
            'avgResponse',
            'medianResponse',
            'p95Response',
            'modelLoadAvg',
            'cameraStartAvg',
            'accuracyPercent',
            'lowConfidenceRate',
            'failureCounts',
            'survey',
            'perAnimal',
            'openVocabCounts',
            'openVocabTypes'
        ));
    }

    public function csv(Request $request)
    {
        $metrics = $this->buildMetrics();

        $rows = [];
        foreach ($metrics as $label => $value) {
            $rows[] = ['metric' => $label, 'value' => $value];
        }

        $fileName = 'ecosight-performance-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($output, ['metric', 'value']);
            foreach ($rows as $row) {
                fputcsv($output, $row);
            }
            fclose($output);
        }, $fileName, ['Content-Type' => 'text/csv']);
    }

    private function buildMetrics(): array
    {
        $responseValues = Scan::orderBy('response_time_ms')->pluck('response_time_ms')->map(fn ($v) => (int) $v)->values();

        $lowConfidenceCount = ScannerEvent::where('type', 'low-confidence')->count();
        $positiveDetections = Scan::count();
        $eventDenominator = $lowConfidenceCount + $positiveDetections;
        $feedbackTotal = ScanFeedback::count();
        $correctFeedback = ScanFeedback::where('is_correct', true)->count();
        $survey = SurveyResponse::query()->selectRaw(
            'AVG(ease_rating) as ease, AVG(speed_rating) as speed, AVG(clarity_rating) as clarity, AVG(usefulness_rating) as usefulness, COUNT(*) as total'
        )->first();

        return [
            'total_scans' => $positiveDetections,
            'avg_response_time_ms' => round($responseValues->avg() ?? 0, 1),
            'median_response_time_ms' => $this->percentile($responseValues, 50),
            'p95_response_time_ms' => $this->percentile($responseValues, 95),
            'avg_model_load_ms' => round((float) ScannerEvent::where('type', 'model-loaded')->whereNotNull('model_load_ms')->avg('model_load_ms') ?? 0, 1),
            'avg_camera_start_ms' => round((float) ScannerEvent::where('type', 'camera-started')->whereNotNull('camera_start_ms')->avg('camera_start_ms') ?? 0, 1),
            'feedback_total' => $feedbackTotal,
            'correct_feedback_percent' => $feedbackTotal > 0 ? round(($correctFeedback / $feedbackTotal) * 100, 1) : 'n/a',
            'low_confidence_rate_percent' => $eventDenominator > 0 ? round(($lowConfidenceCount / $eventDenominator) * 100, 1) : 'n/a',
            'survey_ease_rating_avg' => $survey->total ? round((float) $survey->ease, 2) : 'n/a',
            'survey_speed_rating_avg' => $survey->total ? round((float) $survey->speed, 2) : 'n/a',
            'survey_clarity_rating_avg' => $survey->total ? round((float) $survey->clarity, 2) : 'n/a',
            'survey_usefulness_rating_avg' => $survey->total ? round((float) $survey->usefulness, 2) : 'n/a',
            'open_vocab_runs' => ScannerEvent::where('type', 'open-vocab-run')->count(),
            'open_vocab_candidates_picked' => ScannerEvent::where('type', 'open-vocab-candidate-picked')->count(),
            'extended_offered' => ScannerEvent::where('type', 'extended-offered')->count(),
            'extended_downloaded' => ScannerEvent::where('type', 'extended-downloaded')->count(),
            'extended_candidates_picked' => ScannerEvent::where('type', 'extended-candidate-picked')->count(),
            'out_of_scope_animals' => ScannerEvent::where('type', 'out-of-scope-animal')->count(),
            'animals_added_from_scan' => ScannerEvent::where('type', 'animal-added-from-scan')->count(),
            'lookup_failures' => ScannerEvent::where('type', 'lookup-failed')->count(),
        ];
    }

    private function perAnimalStats(): Collection
    {
        $groups = Scan::query()
            ->whereNotNull('animal_id')
            ->selectRaw('animal_id, confidence')
            ->get()
            ->groupBy('animal_id');

        return $groups->map(function ($scans, $animalId) {
            $animal = Animal::find($animalId);
            $values = $scans->pluck('confidence')->map(fn ($value) => (float) $value);

            $count = $values->count();
            $mean = $count > 0 ? $values->avg() : 0;
            $variance = $count > 1
                ? $values->map(fn ($value) => ($value - $mean) ** 2)->sum() / ($count - 1)
                : 0;
            $stdDev = sqrt($variance);

            return [
                'animal_id' => $animalId,
                'common_name' => $animal?->common_name ?? 'Unknown',
                'scans' => $count,
                'avg_confidence' => round($mean, 4),
                'std_dev' => round($stdDev, 4),
                'avg_response_ms' => round($scans->avg(fn ($scan) => (int) $scan->response_time_ms) ?? 0, 1),
            ];
        })->values()->sortByDesc('avg_confidence')->values();
    }

    private function percentile(Collection $values, int $percentile): ?float
    {
        $count = $values->count();
        if ($count === 0) {
            return null;
        }

        $sorted = $values->sort()->values();
        $rank = (int) ceil(($percentile / 100) * $count) - 1;
        $rank = max(0, min($count - 1, $rank));

        return (float) $sorted->get($rank);
    }
}
