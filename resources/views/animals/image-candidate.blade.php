@extends('layouts.public')

@section('content')
<div class="mx-auto max-w-4xl px-4 py-12 sm:px-6 lg:px-8">
    <a href="{{ route('animals.show', $animal) }}" class="text-sm font-bold text-emerald-700 hover:text-emerald-900">← Back to {{ $animal->common_name }}</a>

    <div class="mt-5">
        <p class="text-sm font-bold uppercase tracking-[0.2em] text-emerald-700">Wikimedia Commons</p>
        <h1 class="mt-2 text-3xl font-black text-slate-900">Choose an image for {{ $animal->common_name }}</h1>
        <p class="mt-2 text-sm leading-6 text-slate-600">EcoSight copies the selected photo as a local WebP, records its licence and credit, and never keeps the temporary Wikimedia link.</p>
    </div>

    @if ($lookupError)
        <div class="mt-6 rounded-2xl border border-red-200 bg-red-50 p-5 text-sm text-red-800">{{ $lookupError }}</div>
        <div class="mt-4"><a href="{{ route('animals.image.candidate', $animal) }}" class="font-bold text-emerald-700 hover:text-emerald-900">Try again</a></div>
    @elseif (! $candidate)
        <div class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm leading-6 text-amber-900">
            No freely licensed photo passed the checks for {{ $animal->common_name }}. You can upload your own image instead.
        </div>
        <div class="mt-4"><a href="{{ route('animals.show', $animal) }}" class="font-bold text-emerald-700 hover:text-emerald-900">Back to animal</a></div>
    @else
        <div class="mt-7 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="aspect-[16/10] bg-slate-100">
                <img src="{{ $candidate['preview_url'] }}" alt="{{ $candidate['alt'] }}" class="h-full w-full object-contain">
            </div>
            <div class="p-6">
                <dl class="grid gap-4 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-bold uppercase tracking-wider text-slate-400">Credit</dt>
                        <dd class="mt-1 text-slate-700">{{ $candidate['credit'] ?: 'Wikimedia Commons' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-bold uppercase tracking-wider text-slate-400">Licence</dt>
                        <dd class="mt-1 text-slate-700">{{ $candidate['license'] }}</dd>
                    </div>
                </dl>
                <p class="mt-4 text-xs leading-5 text-slate-500">Preview shown directly from Wikimedia Commons. The file is checked and downloaded only after you confirm.</p>

                <form method="POST" action="{{ route('animals.image.from-wikipedia', $animal) }}" class="mt-6 space-y-4">
                    @csrf
                    <input type="hidden" name="candidate_token" value="{{ $candidate['token'] }}">
                    <div>
                        <label for="image_alt" class="form-label">Alt text</label>
                        <input id="image_alt" name="image_alt" value="{{ old('image_alt', $candidate['alt']) }}" maxlength="255" class="form-input">
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <button class="rounded-xl bg-emerald-700 px-5 py-3 text-sm font-bold text-white hover:bg-emerald-800">Use this image</button>
                        <a href="{{ $candidate['source_url'] }}" target="_blank" rel="noopener noreferrer" class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-bold text-slate-700 hover:bg-slate-50">View source</a>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
@endsection
