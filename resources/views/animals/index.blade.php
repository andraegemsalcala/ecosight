@extends('layouts.public')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
    <div class="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
        <div>
            <p class="text-sm font-bold uppercase tracking-[0.2em] text-emerald-700">Animal Library</p>
            <h1 class="mt-2 text-4xl font-black text-slate-900">Learn about supported animals</h1>
            <p class="mt-3 max-w-2xl text-slate-600">Browse the same land-animal classes used by the scanner. Combine filters to find a species, compare conservation categories, or review user-added records.</p>
        </div>
        @auth
            <a href="{{ route('animals.create') }}" class="rounded-xl bg-emerald-700 px-5 py-3 text-center text-sm font-bold text-white hover:bg-emerald-800">Add animal</a>
        @endauth
    </div>

    <div class="mt-8 grid gap-8 lg:grid-cols-[280px_minmax(0,1fr)]">
        <aside class="hidden lg:block">
            <form method="GET" action="{{ route('animals.index') }}" class="sticky top-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                @include('animals.filter-fields', ['prefix' => 'desktop'])
            </form>
        </aside>

        <div class="min-w-0">
            <details class="group rounded-2xl border border-slate-200 bg-white shadow-sm lg:hidden">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 font-black text-slate-800">
                    <span>Filters</span>
                    <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-black text-emerald-800">{{ count($activeFilters) }} active</span>
                </summary>
                <form method="GET" action="{{ route('animals.index') }}" class="border-t border-slate-100 p-5">
                    @include('animals.filter-fields', ['prefix' => 'mobile'])
                </form>
            </details>

            <div class="mt-4 flex flex-col justify-between gap-3 sm:flex-row sm:items-center lg:mt-0">
                <p class="text-sm text-slate-600">
                    Showing <span class="font-bold text-slate-900">{{ $animals->firstItem() ?? 0 }}–{{ $animals->lastItem() ?? 0 }}</span>
                    of <span class="font-bold text-slate-900">{{ $animals->total() }}</span> animals
                </p>
                @if ($activeFilters !== [])
                    <a href="{{ route('animals.index') }}" class="text-sm font-bold text-emerald-700 hover:text-emerald-900">Clear all filters</a>
                @endif
            </div>

            @if ($activeFilters !== [])
                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach ($activeFilters as $filter)
                        <a href="{{ $filter['remove_url'] }}" class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-900 hover:bg-emerald-100" aria-label="Remove filter: {{ $filter['label'] }}">
                            {{ $filter['label'] }}
                            <span aria-hidden="true">×</span>
                        </a>
                    @endforeach
                </div>
            @endif

            <div class="mt-6 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                @forelse ($animals as $animal)
                    @php($imageSrc = $animal->image_src)
                    @php($dimensions = $animal->imageDimensions())
                    <article class="motion-card relative rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div class="relative aspect-[4/3] overflow-hidden rounded-t-2xl bg-gradient-to-br from-emerald-800 to-lime-600">
                            @if ($imageSrc)
                                <img src="{{ $imageSrc }}"
                                     alt="{{ $animal->image_alt }}"
                                     width="{{ $dimensions['width'] ?? 1200 }}"
                                     height="{{ $dimensions['height'] ?? 900 }}"
                                     loading="lazy"
                                     class="h-full w-full object-cover">
                            @else
                                <div class="flex h-full items-center justify-center p-8">
                                    <img src="{{ asset('images/logo-mark.png') }}"
                                         alt=""
                                         width="512"
                                         height="512"
                                         loading="lazy"
                                         class="h-full w-auto max-w-full object-contain drop-shadow-lg">
                                </div>
                            @endif
                            <span class="absolute left-4 top-4 flex flex-col items-start gap-1.5">
                                <span class="rounded-full bg-white/20 px-2.5 py-1 text-[11px] font-black uppercase tracking-wider text-white">{{ $animal->coco_label }}</span>
                                <span class="rounded-full bg-slate-900/60 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-white">{{ strtoupper($animal->model_source) }}</span>
                                @if ($animal->isUserAdded() && ! $animal->is_reviewed)
                                    <span class="rounded-full bg-amber-300 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-amber-950">Unreviewed</span>
                                @endif
                            </span>
                            @unless ($animal->is_scannable)
                                <span class="absolute bottom-4 left-4 rounded-full bg-amber-300 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-amber-950">Library only</span>
                            @endunless
                        </div>
                        @auth
                            <div class="absolute right-4 top-4 z-20">
                                @include('animals.image-menu', ['animal' => $animal])
                            </div>
                        @endauth
                        <div class="p-6">
                            <div class="flex flex-wrap gap-2 text-[11px] font-bold uppercase tracking-wider text-slate-400">
                                @if ($animal->animalClassLabel())
                                    <span>{{ $animal->animalClassLabel() }}</span>
                                @endif
                                @if ($animal->is_domestic)
                                    <span>Domestic</span>
                                @endif
                            </div>
                            <h2 class="mt-2 text-2xl font-black">{{ $animal->common_name }}</h2>
                            <p class="text-sm italic text-slate-500">{{ $animal->scientific_name }}</p>
                            <p class="mt-3 line-clamp-3 text-sm leading-6 text-slate-600">{{ $animal->description }}</p>
                            <p class="mt-4 text-xs font-bold uppercase tracking-wider text-emerald-700">{{ $animal->conservation_status }}</p>
                            <a href="{{ route('animals.show', $animal) }}" class="mt-5 inline-flex font-bold text-emerald-700 hover:text-emerald-900">View details →</a>
                        </div>
                    </article>
                @empty
                    <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center md:col-span-2 xl:col-span-3">
                        <p class="text-lg font-black text-slate-700">No animals matched your filters</p>
                        <p class="mt-1 text-sm text-slate-500">Try removing a filter or searching for a different name.</p>
                        <a href="{{ route('animals.index') }}" class="mt-5 inline-flex rounded-xl bg-brand-dark px-5 py-2.5 text-sm font-bold text-white hover:bg-emerald-900">Clear filters</a>
                    </div>
                @endforelse
            </div>

            <div class="mt-8">{{ $animals->links() }}</div>
        </div>
    </div>
</div>
@endsection
