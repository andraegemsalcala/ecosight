@extends('layouts.public')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
    <div class="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
        <div>
            <p class="text-sm font-bold uppercase tracking-[0.2em] text-emerald-700">System report</p>
            <h1 class="mt-2 text-4xl font-black">Performance overview</h1>
            <p class="mt-3 max-w-2xl text-slate-600">Aggregated across all EcoSight users from saved scans, client-side scanner events, and feedback.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('performance.csv') }}" class="rounded-xl bg-slate-900 px-5 py-3 text-center text-sm font-bold text-white hover:bg-slate-800">Download report CSV</a>
            <a href="{{ route('scans.export') }}" class="rounded-xl border border-slate-300 px-5 py-3 text-center text-sm font-bold text-slate-700 hover:bg-slate-50">Download my scans CSV</a>
        </div>
    </div>

    <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <div class="stat-card"><span>Avg. response time</span><strong>{{ number_format($avgResponse, 0) }} ms</strong></div>
        <div class="stat-card"><span>Median response</span><strong>{{ $medianResponse !== null ? number_format($medianResponse, 0) . ' ms' : '—' }}</strong></div>
        <div class="stat-card"><span>P95 response</span><strong>{{ $p95Response !== null ? number_format($p95Response, 0) . ' ms' : '—' }}</strong></div>
        <div class="stat-card"><span>Model load (avg)</span><strong>{{ number_format($modelLoadAvg, 0) }} ms</strong></div>
        <div class="stat-card"><span>Camera start (avg)</span><strong>{{ number_format($cameraStartAvg, 0) }} ms</strong></div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-black">Recognition accuracy</h2>
            <p class="mt-1 text-sm text-slate-500">Correct vs incorrect scan feedback.</p>
            <p class="mt-6 text-5xl font-black text-brand-leaf">{{ $accuracyPercent !== null ? $accuracyPercent : '—' }}<span class="text-xl">%</span></p>
            <p class="mt-3 text-sm text-slate-600">Of feedback responses, this share said the recognition was correct.</p>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-black">Low-confidence rate</h2>
            <p class="mt-1 text-sm text-slate-500">Low-confidence events out of detections + low-confidence events.</p>
            <p class="mt-6 text-5xl font-black text-amber-600">{{ $lowConfidenceRate !== null ? $lowConfidenceRate : '—' }}<span class="text-xl">%</span></p>
            <p class="mt-3 text-sm text-slate-600">A higher rate means users are often left without a confident result.</p>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-black">Usability survey</h2>
            <p class="mt-1 text-sm text-slate-500">Average ratings out of 5 across {{ $survey->total ?? 0 }} response(s).</p>
            <dl class="mt-6 space-y-3 text-sm">
                <div class="flex justify-between"><dt class="font-bold text-slate-600">Ease of use</dt><dd>{{ $survey->total ? number_format((float) $survey->ease, 1) : '—' }}</dd></div>
                <div class="flex justify-between"><dt class="font-bold text-slate-600">Speed</dt><dd>{{ $survey->total ? number_format((float) $survey->speed, 1) : '—' }}</dd></div>
                <div class="flex justify-between"><dt class="font-bold text-slate-600">Clarity</dt><dd>{{ $survey->total ? number_format((float) $survey->clarity, 1) : '—' }}</dd></div>
                <div class="flex justify-between"><dt class="font-bold text-slate-600">Usefulness</dt><dd>{{ $survey->total ? number_format((float) $survey->usefulness, 1) : '—' }}</dd></div>
            </dl>
        </section>
    </div>

    @php
        $openVocabLabels = [
            'open-vocab-run' => 'Species not in library (open vocabulary run)',
            'open-vocab-candidate-picked' => 'Closest-match candidate picked',
            'extended-offered' => 'Extended list offered',
            'extended-downloaded' => 'Extended list downloaded',
            'extended-declined' => 'Extended list declined',
            'extended-candidate-picked' => 'Extended candidate picked',
            'extended-none-of-these' => 'Extended list: none of these',
            'out-of-scope-animal' => 'Out-of-scope animal detected',
            'animal-added-from-scan' => 'Animals added from a scan',
            'lookup-failed' => 'Species lookups that failed',
        ];
    @endphp

    <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-xl font-black">Open vocabulary</h2>
        <p class="mt-1 text-sm text-slate-500">How often the scanner went beyond the Animal Library and what users did next.</p>
        <div class="mt-4 overflow-x-auto">
            <table class="w-full min-w-[480px] text-left text-sm">
                <thead class="border-b border-slate-200 text-xs uppercase tracking-wider text-slate-500"><tr><th class="py-3">Event</th><th>Count</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($openVocabTypes as $type)
                        <tr>
                            <td class="py-3">{{ $openVocabLabels[$type] ?? $type }}</td>
                            <td>{{ $openVocabCounts[$type] ?? 0 }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl font-black">Response time per animal</h2>
                    <p class="mt-1 text-sm text-slate-500">Average detection response in milliseconds.</p>
                </div>
            </div>
            <div class="relative mt-6" style="height: 280px">
                <div id="chart-skeleton-1" class="skeleton absolute inset-0 z-10 flex items-center justify-center rounded-xl text-sm font-bold text-slate-400">Loading chart…</div>
                <canvas id="responseChart"></canvas>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl font-black">Accuracy vs low-confidence</h2>
                    <p class="mt-1 text-sm text-slate-500">Correct feedback share against low-confidence share.</p>
                </div>
            </div>
            <div class="relative mt-6" style="height: 280px">
                <div id="chart-skeleton-2" class="skeleton absolute inset-0 z-10 flex items-center justify-center rounded-xl text-sm font-bold text-slate-400">Loading chart…</div>
                <canvas id="accuracyChart"></canvas>
            </div>
        </section>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-black">Detection failures by reason</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full min-w-[420px] text-left text-sm">
                    <thead class="border-b border-slate-200 text-xs uppercase tracking-wider text-slate-500"><tr><th class="py-3">Reason</th><th>Events</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($failureCounts as $failure)
                            <tr><td class="py-3 font-mono">{{ $failure->reason }}</td><td>{{ $failure->count }}</td></tr>
                        @empty
                            <tr><td colspan="2" class="py-8 text-center text-slate-500">No failure events recorded.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-black">Confidence per animal</h2>
            <p class="mt-1 text-sm text-slate-500">Average confidence with standard deviation.</p>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full min-w-[480px] text-left text-sm">
                    <thead class="border-b border-slate-200 text-xs uppercase tracking-wider text-slate-500"><tr><th class="py-3">Animal</th><th>Scans</th><th>Avg. confidence</th><th>Std dev</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($perAnimal as $row)
                            <tr>
                                <td class="py-3 font-bold">{{ $row['common_name'] }}</td>
                                <td>{{ $row['scans'] }}</td>
                                <td>{{ number_format($row['avg_confidence'] * 100, 1) }}%</td>
                                <td>± {{ number_format($row['std_dev'] * 100, 1) }}%</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-8 text-center text-slate-500">No saved scans yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const perAnimal = @json($perAnimal);
    const responseLabels = perAnimal.map((row) => row.common_name);
    const responseValues = perAnimal.map((row) => row.avg_response_ms);
    const accuracyNow = @json($accuracyPercent) ?? 0;
    const lowConfidenceNow = @json($lowConfidenceRate) ?? 0;

    new Chart(document.getElementById('responseChart'), {
        type: 'bar',
        data: {
            labels: responseLabels,
            datasets: [{ label: 'Avg. response (ms)', data: responseValues, borderWidth: 1 }]
        },
        options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
    });
    requestAnimationFrame(() => { document.getElementById('chart-skeleton-1').classList.add('hidden'); });

    new Chart(document.getElementById('accuracyChart'), {
        type: 'doughnut',
        data: {
            labels: ['Correctly identified (feedback)', 'Low-confidence rate'],
            datasets: [{ data: [accuracyNow, lowConfidenceNow], borderWidth: 2 }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
    });
    requestAnimationFrame(() => { document.getElementById('chart-skeleton-2').classList.add('hidden'); });
</script>
@endpush