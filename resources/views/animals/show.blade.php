@extends('layouts.public')

@section('content')
<div class="mx-auto max-w-6xl px-4 py-12 sm:px-6 lg:px-8">
    <div class="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
        <div>
            <p class="text-sm font-bold uppercase tracking-[0.2em] text-emerald-700">{{ $animal->coco_label }}</p>
            <h1 class="mt-2 text-4xl font-black">{{ $animal->common_name }}</h1>
            <p class="mt-2 italic text-slate-500">{{ $animal->scientific_name }}</p>
            @if ($animal->isUserAdded() && ! $animal->is_reviewed)
                <p class="mt-3 inline-flex rounded-full bg-amber-300 px-3 py-1 text-[11px] font-black uppercase tracking-wider text-amber-950">Unreviewed</p>
            @endif
            @if ($animal->groupLabel())
                <p class="mt-2 text-xs font-bold uppercase tracking-wider text-slate-400">{{ $animal->groupLabel() }}</p>
            @endif
        </div>
        @auth
            <div class="flex flex-wrap gap-2">
                @if ($animal->isUserAdded())
                    <form method="POST" action="{{ route('animals.review', $animal) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="is_reviewed" value="{{ $animal->is_reviewed ? '0' : '1' }}">
                        <button class="rounded-xl border border-emerald-700 px-4 py-2 text-sm font-bold text-emerald-800">
                            {{ $animal->is_reviewed ? 'Send back for review' : 'Mark as reviewed' }}
                        </button>
                    </form>
                @endif
                <a href="{{ route('animals.edit', $animal) }}" class="rounded-xl border border-emerald-700 px-4 py-2 text-sm font-bold text-emerald-800">Edit</a>
                <form method="POST" action="{{ route('animals.destroy', $animal) }}" onsubmit="return confirm('Delete this animal and its conservation actions?')">
                    @csrf
                    @method('DELETE')
                    <button class="rounded-xl bg-red-600 px-4 py-2 text-sm font-bold text-white">Delete</button>
                </form>
            </div>
        @endauth
    </div>

    @php($imageDimensions = $animal->imageDimensions())
    <figure class="relative mt-8 rounded-2xl border border-slate-200 bg-gradient-to-br from-emerald-800 to-lime-600 shadow-sm">
        @if ($animal->image_src)
            <img src="{{ $animal->image_src }}"
                 alt="{{ $animal->image_alt }}"
                 width="{{ $imageDimensions['width'] ?? 1200 }}"
                 height="{{ $imageDimensions['height'] ?? 900 }}"
                 class="max-h-[32rem] w-full rounded-t-2xl object-cover">
        @else
            <div class="flex min-h-[16rem] items-center justify-center rounded-t-2xl p-10">
                <img src="{{ asset('images/logo-mark.png') }}" alt="" width="512" height="512" class="h-40 w-40 object-contain drop-shadow-lg">
            </div>
        @endif
        <figcaption class="flex flex-col gap-3 rounded-b-2xl bg-emerald-950/90 px-5 py-4 text-sm text-emerald-50 sm:flex-row sm:items-center sm:justify-between">
            <span>
                @if ($animal->image_src)
                    {{ $animal->image_credit ?: 'Image credit not recorded' }}
                    @if ($animal->image_license)
                        · {{ $animal->image_license }}
                    @endif
                    @if ($animal->image_source_url)
                        · <a href="{{ $animal->image_source_url }}" target="_blank" rel="noopener noreferrer" class="underline hover:text-white">Source</a>
                    @endif
                @else
                    No photo yet. Add one from Wikimedia Commons or upload your own.
                @endif
            </span>
            @auth
                <div class="relative shrink-0">
                    @include('animals.image-menu', ['animal' => $animal])
                </div>
            @endauth
        </figcaption>
    </figure>

    @if ($animal->isUserAdded())
        <div class="mt-6 rounded-2xl border border-amber-300 bg-amber-50 p-5 text-amber-900">
            <p class="font-black">{{ $animal->is_reviewed ? 'Reviewed' : 'Waiting for review' }}</p>
            <p class="mt-1 text-sm leading-6">
                This animal was added by a user from a scan, so its record has not been checked by a reviewer yet.
                @if ($animal->addedBy)
                    Added by {{ $animal->addedBy->name }}.
                @endif
            </p>
            @if ($animal->source_url)
                <p class="mt-2 text-xs">
                    Source: <a href="{{ $animal->source_url }}" target="_blank" rel="noopener noreferrer" class="underline">{{ $animal->source_url }}</a>
                    @if ($animal->source_note)
                        · {{ $animal->source_note }}
                    @endif
                </p>
            @endif
        </div>
    @endif

    <div class="mt-8 grid gap-6 lg:grid-cols-3">
        <div class="rounded-2xl bg-emerald-900 p-7 text-white lg:col-span-2">
            <h2 class="text-xl font-black">About this animal</h2>
            <p class="mt-4 leading-7 text-emerald-50/80">{{ $animal->description }}</p>
            <div class="mt-7 grid gap-5 sm:grid-cols-2">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-emerald-300">Habitat</p>
                    <p class="mt-2 text-sm leading-6 text-white/80">{{ $animal->habitat }}</p>
                </div>
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-emerald-300">Climate impact</p>
                    <p class="mt-2 text-sm leading-6 text-white/80">{{ $animal->climate_impact }}</p>
                </div>
            </div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-7 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Conservation status</p>
            <p class="mt-3 text-xl font-black text-slate-900">{{ $animal->conservation_status }}</p>
            <p class="mt-6 text-xs font-bold uppercase tracking-wider text-slate-500">Detected by</p>
            <p class="mt-3 rounded-lg bg-slate-100 px-3 py-2 font-mono text-sm text-slate-700">{{ strtoupper($animal->model_source) }} · {{ $animal->coco_label }}</p>
            @unless ($animal->is_scannable)
                <p class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-xs font-bold text-amber-800">Not detected by the scanner</p>
            @endunless
            @if ($animal->varieties->isNotEmpty())
                <p class="mt-6 text-xs font-bold uppercase tracking-wider text-slate-500">Breeds and varieties</p>
                <ul class="mt-3 space-y-1 text-sm text-slate-700">
                    @foreach ($animal->varieties as $variety)
                        <li class="flex items-center justify-between gap-3 rounded-lg bg-slate-50 px-3 py-2">
                            <span>{{ $variety->label }}</span>
                            @if ($variety->clip_names)
                                <span class="text-xs text-slate-400" title="{{ implode(', ', $variety->clip_names) }}">{{ count($variety->clip_names) }} prompts</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    <section class="mt-10">
        <h2 class="text-2xl font-black">Practical conservation actions</h2>
        <div class="mt-5 grid gap-5 md:grid-cols-2">
            @foreach ($actions as $action)
                <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex items-start justify-between gap-4">
                        <h3 class="font-black">{{ $action->title }}</h3>
                        <span class="{{ $completedActionIds->contains($action->id) ? 'bg-emerald-100 text-emerald-800' : 'bg-lime-100 text-lime-800' }} rounded-full px-3 py-1 text-xs font-bold">{{ ucfirst($action->difficulty) }}</span>
                    </div>
                    <p class="mt-3 text-sm leading-6 text-slate-600">{{ $action->description }}</p>
                    <p class="mt-4 text-xs font-bold uppercase tracking-wider text-emerald-700">{{ $action->category }}</p>
                    @if ($action->animal_id === null)
                        <p class="mt-2 text-xs text-slate-400">General action for every {{ $animal->groupLabel() ?? 'animal' }}.</p>
                    @endif
                    @auth
                        @if ($completedActionIds->contains($action->id))
                            <form method="POST" action="{{ route('actions.complete.destroy', $action) }}" class="mt-4">
                                @csrf
                                @method('DELETE')
                                <button class="rounded-lg border border-emerald-700 px-4 py-2 text-sm font-bold text-emerald-800 hover:bg-emerald-50">Done ✓ Undo</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('actions.complete', $action) }}" class="mt-4">
                                @csrf
                                <button class="rounded-lg bg-brand-dark px-4 py-2 text-sm font-bold text-white hover:bg-emerald-900">Mark as done</button>
                            </form>
                        @endif
                    @endauth
                </article>
            @endforeach
        </div>
    </section>
</div>
@endsection
