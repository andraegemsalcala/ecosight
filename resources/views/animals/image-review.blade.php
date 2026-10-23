@extends('layouts.public')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <p class="text-sm font-bold uppercase tracking-[0.2em] text-emerald-700">Image review</p>
            <h1 class="mt-2 text-4xl font-black text-slate-900">Images waiting for a second look</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">Check that the photo matches the animal, then confirm the credit and licence before marking it reviewed.</p>
        </div>
        <a href="{{ route('animals.index', ['image' => 'with']) }}" class="rounded-xl border border-emerald-700 px-4 py-2 text-center text-sm font-bold text-emerald-800 hover:bg-emerald-50">Browse images</a>
    </div>

    <div class="mt-8 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($animals as $animal)
            <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="aspect-[4/3] bg-slate-100">
                    <img src="{{ $animal->image_src }}" alt="{{ $animal->image_alt }}" loading="lazy" class="h-full w-full object-cover">
                </div>
                <div class="p-5">
                    <h2 class="text-xl font-black">{{ $animal->common_name }}</h2>
                    <p class="text-sm italic text-slate-500">{{ $animal->scientific_name }}</p>
                    <p class="mt-3 text-xs text-slate-500">{{ $animal->image_credit ?: 'Credit not recorded' }} · {{ $animal->image_license ?: 'Licence not recorded' }}</p>
                    @if ($animal->image_source_url)
                        <a href="{{ $animal->image_source_url }}" target="_blank" rel="noopener noreferrer" class="mt-2 inline-block text-xs font-bold text-emerald-700 hover:text-emerald-900">View source</a>
                    @endif
                    <form method="POST" action="{{ route('animals.image.review', $animal) }}" class="mt-4 flex flex-wrap gap-2">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="reviewed" value="1">
                        <button class="rounded-xl bg-emerald-700 px-4 py-2 text-sm font-bold text-white hover:bg-emerald-800">Mark reviewed</button>
                        <a href="{{ route('animals.image.candidate', $animal) }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50">Replace</a>
                    </form>
                </div>
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center md:col-span-2 xl:col-span-3">
                <p class="text-lg font-black text-slate-700">No images need review</p>
                <p class="mt-1 text-sm text-slate-500">Images you import from Wikimedia are marked reviewed after confirmation. Use the menu on an animal to send one back.</p>
            </div>
        @endforelse
    </div>

    <div class="mt-8">{{ $animals->links() }}</div>
</div>
@endsection
