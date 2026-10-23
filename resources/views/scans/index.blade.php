@extends('layouts.public')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
    <div class="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
        <div>
            <p class="text-sm font-bold uppercase tracking-[0.2em] text-emerald-700">My Scans</p>
            <h1 class="mt-2 text-4xl font-black">Scan history</h1>
            <p class="mt-3 text-slate-600">Only your saved scans are shown here.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('scans.export') }}" class="rounded-xl border border-slate-300 px-5 py-3 text-center text-sm font-bold text-slate-700 hover:bg-slate-50">Export CSV</a>
            <a href="{{ route('scanner') }}" class="rounded-xl bg-brand-dark px-5 py-3 text-center text-sm font-bold text-white hover:bg-emerald-900">New scan</a>
        </div>
    </div>

    <form method="GET" action="{{ route('scans.index') }}" class="mt-8 flex flex-col gap-3 sm:flex-row sm:items-end">
        <div>
            <label for="from" class="block text-xs font-bold uppercase tracking-wider text-slate-500">From</label>
            <input id="from" type="date" name="from" value="{{ $from ?? '' }}" class="mt-1 rounded-xl border-slate-300 px-4 py-2.5">
        </div>
        <div>
            <label for="to" class="block text-xs font-bold uppercase tracking-wider text-slate-500">To</label>
            <input id="to" type="date" name="to" value="{{ $to ?? '' }}" class="mt-1 rounded-xl border-slate-300 px-4 py-2.5">
        </div>
        <button class="rounded-xl bg-slate-900 px-5 py-2.5 font-bold text-white hover:bg-slate-800">Filter</button>
        @if (($from ?? null) || ($to ?? null))
            <a href="{{ route('scans.index') }}" class="rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50">Clear</a>
        @endif
    </form>

    <div class="mt-5 overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full min-w-[980px] text-left text-sm">
            <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wider text-slate-500"><tr><th class="px-5 py-4">Animal</th><th>Model</th><th>Confidence</th><th>Response</th><th>Notes</th><th>Date</th><th></th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($scans as $scan)
                    <tr>
                        <td class="px-5 py-4 font-bold">
                            @if ($scan->animal)
                                <a href="{{ route('animals.show', $scan->animal) }}" class="text-emerald-700 hover:text-emerald-900">{{ $scan->animal->common_name }}</a>
                            @else
                                {{ $scan->detected_label }}
                            @endif
                            @if ($scan->variety_label)
                                <span class="mt-1 block text-xs font-normal text-slate-500">{{ $scan->variety_label }}@if ($scan->variety_confidence) · {{ number_format((float) $scan->variety_confidence * 100, 0) }}%@endif @if ($scan->variety_confirmed)<span class="font-bold text-emerald-700">confirmed</span>@endif</span>
                            @endif
                        </td>
                        <td class="uppercase text-xs font-bold text-slate-500">{{ $scan->model_source ?? 'coco-ssd' }}</td>
                        <td>
                            {{ number_format((float) $scan->confidence * 100, 1) }}%
                            @if ($scan->user_confirmed)
                                <span class="mt-1 block text-[10px] font-bold uppercase text-emerald-700">user confirmed</span>
                            @endif
                        </td>
                        <td>{{ $scan->response_time_ms }} ms</td>
                        <td>{{ $scan->notes ?? '—' }}</td>
                        <td>{{ $scan->created_at->format('M d, Y h:i A') }}</td>
                        <td class="pr-5 text-right"><form method="POST" action="{{ route('scans.destroy', $scan) }}" onsubmit="return confirm('Delete this scan?')">@csrf @method('DELETE')<button class="font-bold text-red-600">Delete</button></form></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-12 text-center">
                        <p class="font-black text-slate-700">No scans found</p>
                        <p class="mt-1 text-sm text-slate-500">{{ ($from ?? null) || ($to ?? null) ? 'Try adjusting the date filter.' : 'Save your first scan from the scanner to see it here.' }}</p>
                        <a href="{{ route('scanner') }}" class="mt-5 inline-flex rounded-xl bg-brand-dark px-5 py-2.5 text-sm font-bold text-white hover:bg-emerald-900">Open the scanner</a>
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-8">{{ $scans->links() }}</div>
</div>
@endsection
