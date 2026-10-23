<?php

namespace App\Http\Controllers;

use App\Models\Animal;
use App\Models\ConservationAction;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();
        $scans = $user->scans()->with('animal');

        $totalScans = (clone $scans)->count();
        $uniqueSpecies = (clone $scans)->whereNotNull('animal_id')->distinct('animal_id')->count('animal_id');
        $averageConfidence = (float) ((clone $scans)->avg('confidence') ?? 0);
        $averageResponse = (float) ((clone $scans)->avg('response_time_ms') ?? 0);

        $mostScannedAnimal = $user->scans()
            ->with('animal')
            ->selectRaw('animal_id, COUNT(*) as scan_count')
            ->whereNotNull('animal_id')
            ->groupBy('animal_id')
            ->orderByDesc('scan_count')
            ->first()?->animal;

        $recentScans = $user->scans()
            ->with('animal')
            ->latest()
            ->take(8)
            ->get();

        $chartRows = $user->scans()
            ->with('animal:id,common_name')
            ->whereNotNull('animal_id')
            ->selectRaw('animal_id, COUNT(*) as scan_count')
            ->groupBy('animal_id')
            ->orderByDesc('scan_count')
            ->get();

        $chartLabels = $chartRows->map(fn ($row) => $row->animal?->common_name ?? 'Unknown')->values();
        $chartValues = $chartRows->pluck('scan_count')->values();

        $actionCount = ConservationAction::whereNotNull('animal_id')->count();
        $todayAction = $actionCount > 0
            ? ConservationAction::with('animal')
                ->whereNotNull('animal_id')
                ->orderBy('id')
                ->skip(Carbon::now()->dayOfYear % $actionCount)
                ->first()
            : null;

        $completions = $user->actionCompletions()
            ->with('conservationAction.animal')
            ->latest('completed_at')
            ->get();

        $actionsCompleted = $completions->count();
        $currentDayStreak = $this->currentDayStreak($completions);
        $animalsCovered = $completions->pluck('conservationAction.animal_id')->unique()->count();
        $totalAnimals = Animal::query()->scannable()->count();

        $todayCompleted = $todayAction
            ? $user->actionCompletions()->where('conservation_action_id', $todayAction->id)->exists()
            : false;

        return view('dashboard.index', compact(
            'totalScans',
            'uniqueSpecies',
            'averageConfidence',
            'averageResponse',
            'mostScannedAnimal',
            'recentScans',
            'chartLabels',
            'chartValues',
            'todayAction',
            'completions',
            'actionsCompleted',
            'currentDayStreak',
            'animalsCovered',
            'totalAnimals',
            'todayCompleted'
        ));
    }

    private function currentDayStreak($completions): int
    {
        $dates = $completions
            ->pluck('completed_at')
            ->map(fn ($completedAt) => $completedAt?->toDateString())
            ->filter()
            ->flip();

        if (! $dates->has(Carbon::today()->toDateString())) {
            return 0;
        }

        $streak = 1;
        for ($day = 1; $dates->has(Carbon::today()->subDays($day)->toDateString()); $day++) {
            $streak++;
        }

        return $streak;
    }
}
