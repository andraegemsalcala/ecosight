@extends('layouts.public')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
    <p class="text-sm font-bold uppercase tracking-[0.2em] text-emerald-700">Photo credits</p>
    <h1 class="mt-2 text-4xl font-black text-slate-900">Image credits and licences</h1>
    <p class="mt-3 max-w-3xl text-slate-600">EcoSight stores a local copy of each approved animal photo. Wikimedia Commons images remain under the licence shown below; follow the source link for the full attribution page.</p>

    <div class="mt-8 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($animals as $animal)
            <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="aspect-[4/3] bg-slate-100">
                    <img src="{{ $animal->image_src }}" alt="{{ $animal->image_alt }}" loading="lazy" class="h-full w-full object-cover">
                </div>
                <div class="p-5">
                    <h2 class="text-lg font-black">{{ $animal->common_name }}</h2>
                    <p class="mt-2 text-sm text-slate-600">{{ $animal->image_credit ?: 'Credit not recorded' }}</p>
                    <p class="mt-1 text-xs font-bold uppercase tracking-wider text-emerald-700">{{ $animal->image_license ?: 'Licence not recorded' }}</p>
                    @if ($animal->image_source_url)
                        <a href="{{ $animal->image_source_url }}" target="_blank" rel="noopener noreferrer" class="mt-3 inline-block text-xs font-bold text-emerald-700 hover:text-emerald-900">Original source</a>
                    @endif
                </div>
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center md:col-span-2 xl:col-span-3">
                <p class="text-lg font-black text-slate-700">No credited images yet</p>
                <p class="mt-1 text-sm text-slate-500">Add an image from an animal page to populate this page.</p>
            </div>
        @endforelse
    </div>

    <div class="mt-8">{{ $animals->links() }}</div>
</div>
@endsection
