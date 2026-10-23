@extends('layouts.public')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
    <div class="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
        <div>
            <p class="text-sm font-bold uppercase tracking-[0.2em] text-emerald-700">Your EcoSight dashboard</p>
            <h1 class="mt-2 text-4xl font-black">Welcome back, {{ auth()->user()->name }}</h1>
            <p class="mt-3 text-slate-600">Track your scans and keep the learning going.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('survey.create') }}" class="rounded-xl border border-emerald-700 px-5 py-3 text-center text-sm font-bold text-emerald-800 hover:bg-emerald-50">Share feedback</a>
            <a href="{{ route('scanner') }}" class="rounded-xl bg-brand-dark px-5 py-3 text-center font-bold text-white hover:bg-emerald-900">Start a scan</a>
        </div>
    </div>

    <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="stat-card"><span>Total scans</span><strong>{{ $totalScans }}</strong></div>
        <div class="stat-card"><span>Unique species</span><strong>{{ $uniqueSpecies }}</strong></div>
        <div class="stat-card"><span>Avg. confidence</span><strong>{{ number_format($averageConfidence * 100, 1) }}%</strong></div>
        <div class="stat-card"><span>Avg. response</span><strong>{{ number_format($averageResponse, 0) }} ms</strong></div>
    </div>

    <div class="mt-4 grid gap-4 sm:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><span class="text-sm font-bold text-slate-500">Conservation actions done</span><strong class="mt-1 block text-3xl font-black text-brand-dark">{{ $actionsCompleted }}</strong></div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><span class="text-sm font-bold text-slate-500">Current-day streak</span><strong class="mt-1 block text-3xl font-black text-brand-dark">{{ $currentDayStreak }} day{{ $currentDayStreak === 1 ? '' : 's' }}</strong></div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><span class="text-sm font-bold text-slate-500">Animals covered</span><strong class="mt-1 block text-3xl font-black text-brand-dark">{{ $animalsCovered }} / {{ $totalAnimals }}</strong></div>
    </div>

    <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-xl font-black">Achievements</h2>
        <div class="mt-4 flex flex-wrap gap-3">
            @foreach ([
                ['First step', $actionsCompleted >= 1, 'Complete one conservation action.'],
                ['Five actions', $actionsCompleted >= 5, 'Complete five conservation actions.'],
                ['Animal champion', $animalsCovered >= $totalAnimals, 'Take action for every supported animal.'],
            ] as [$label, $earned, $description])
                <span title="{{ $description }}" class="{{ $earned ? 'bg-brand-dark text-white' : 'bg-slate-100 text-slate-400' }} rounded-full px-4 py-2 text-sm font-bold">
                    {{ $earned ? '★ ' : '☆ ' }}{{ $label }}
                </span>
            @endforeach
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl font-black">Scans per animal</h2>
                    <p class="mt-1 text-sm text-slate-500">A simple view of your saved scan history.</p>
                </div>
                @if ($mostScannedAnimal)
                    <span class="rounded-full bg-lime-100 px-3 py-1 text-xs font-bold text-lime-800">Most scanned: {{ $mostScannedAnimal->common_name }}</span>
                @endif
            </div>
            <div class="mt-6" style="height: 280px"><canvas id="scanChart"></canvas></div>
        </section>

        <section class="rounded-2xl bg-emerald-900 p-6 text-white">
            <p class="text-xs font-bold uppercase tracking-[0.2em] text-emerald-300">Today's conservation action</p>
            @if ($todayAction)
                <h2 class="mt-4 text-2xl font-black">{{ $todayAction->title }}</h2>
                <p class="mt-3 text-sm leading-6 text-emerald-50/80">{{ $todayAction->description }}</p>
                <p class="mt-5 text-xs font-bold uppercase tracking-wider text-lime-300">{{ $todayAction->animal->common_name }} · {{ $todayAction->category }}</p>
                @if ($todayCompleted)
                    <form method="POST" action="{{ route('actions.complete.destroy', $todayAction) }}" class="mt-5">
                        @csrf
                        @method('DELETE')
                        <button class="rounded-xl border border-white/40 px-5 py-2.5 text-sm font-bold text-white hover:bg-white/10">Done ✓ Undo</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('actions.complete', $todayAction) }}" class="mt-5">
                        @csrf
                        <button class="rounded-xl bg-white px-5 py-2.5 text-sm font-black text-emerald-900 hover:bg-emerald-50">Mark as done</button>
                    </form>
                @endif
            @else
                <p class="mt-4 text-sm text-emerald-50/80">No conservation action has been added yet.</p>
            @endif
        </section>
    </div>

    <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-black">Recently completed actions</h2>
            <span class="rounded-full bg-lime-100 px-3 py-1 text-xs font-bold text-lime-800">Total: {{ $actionsCompleted }}</span>
        </div>
        <div class="mt-4 overflow-x-auto">
            <table class="w-full min-w-[560px] text-left text-sm">
                <thead class="border-b border-slate-200 text-xs uppercase tracking-wider text-slate-500"><tr><th class="py-3">Action</th><th>Animal</th><th>Completed</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($completions as $completion)
                        <tr>
                            <td class="py-3 font-bold">{{ $completion->conservationAction->title }}</td>
                            <td>
                                @if ($completion->conservationAction->animal)
                                    <a href="{{ route('animals.show', $completion->conservationAction->animal) }}" class="text-emerald-700 hover:text-emerald-900">{{ $completion->conservationAction->animal->common_name }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $completion->completed_at->format('M d, Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-8 text-center">
                            <p class="font-black text-slate-700">No completed actions yet</p>
                            <p class="mt-1 text-sm text-slate-500">Mark an action as done from an animal page or the scanner.</p>
                            <a href="{{ route('animals.index') }}" class="mt-4 inline-flex rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50">Browse the library</a>
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-black">Recent scans</h2>
            <a href="{{ route('scans.index') }}" class="text-sm font-bold text-emerald-700">View all</a>
        </div>
        <div class="mt-5 overflow-x-auto">
            <table class="w-full min-w-[640px] text-left text-sm">
                <thead class="border-b border-slate-200 text-xs uppercase tracking-wider text-slate-500"><tr><th class="py-3">Animal</th><th>Confidence</th><th>Response</th><th>Date</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($recentScans as $scan)
                        <tr><td class="py-3 font-bold">{{ $scan->animal?->common_name ?? $scan->detected_label }}</td><td>{{ number_format((float) $scan->confidence * 100, 1) }}%</td><td>{{ $scan->response_time_ms }} ms</td><td>{{ $scan->created_at->format('M d, Y h:i A') }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="py-8 text-center">
                            <p class="font-black text-slate-700">No scans yet</p>
                            <p class="mt-1 text-sm text-slate-500">Try the scanner with your camera or an uploaded photo.</p>
                            <a href="{{ route('scanner') }}" class="mt-4 inline-flex rounded-xl bg-brand-dark px-5 py-2.5 text-sm font-bold text-white hover:bg-emerald-900">Start a scan</a>
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const chartLabels = @json($chartLabels);
    const chartValues = @json($chartValues);
    new Chart(document.getElementById('scanChart'), {
        type: 'bar',
        data: {
            labels: chartLabels,
            datasets: [{ label: 'Scans', data: chartValues, borderWidth: 1 }]
        },
        options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
    });
</script>
@endpush
