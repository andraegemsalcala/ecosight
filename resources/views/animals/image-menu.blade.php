@php($hasImage = $animal->hasImage())
<details class="group relative">
    <summary class="flex h-9 w-9 cursor-pointer list-none items-center justify-center rounded-full bg-white/90 text-lg font-black text-slate-800 shadow-sm ring-1 ring-white/60 hover:bg-white" aria-label="Image actions for {{ $animal->common_name }}">
        <span aria-hidden="true">⋯</span>
    </summary>
    <div class="absolute right-0 top-full z-30 mt-2 w-72 rounded-2xl border border-slate-200 bg-white p-4 text-left shadow-xl">
        @if ($hasImage)
            <div class="mb-3 flex items-center justify-between gap-2">
                <span class="text-xs font-black uppercase tracking-wider text-slate-400">Current image</span>
                <a href="{{ $animal->image_src }}" target="_blank" rel="noopener noreferrer" class="text-xs font-bold text-emerald-700 hover:text-emerald-900">Open</a>
            </div>
        @endif

        <form method="POST" action="{{ route('animals.image.store', $animal) }}" enctype="multipart/form-data" class="space-y-2">
            @csrf
            <label class="block text-xs font-black uppercase tracking-wider text-slate-500" for="image-{{ $animal->id }}">Upload image</label>
            <input id="image-{{ $animal->id }}" name="image" type="file" accept="image/jpeg,image/png,image/webp" required class="block w-full text-xs text-slate-600 file:mr-2 file:rounded-lg file:border-0 file:bg-emerald-50 file:px-3 file:py-2 file:text-xs file:font-bold file:text-emerald-800">
            <input name="image_alt" value="{{ old('image_alt', $animal->image_alt) }}" placeholder="Short alt text (optional)" class="form-input text-sm">
            <button class="w-full rounded-xl bg-emerald-700 px-3 py-2 text-sm font-bold text-white hover:bg-emerald-800">Upload image</button>
        </form>

        <div class="my-3 border-t border-slate-100"></div>

        <a href="{{ route('animals.image.candidate', $animal) }}" class="block rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-center text-sm font-bold text-emerald-800 hover:bg-emerald-100">
            {{ $hasImage ? 'Replace from Wikimedia' : 'Find on Wikimedia Commons' }}
        </a>

        @if ($hasImage)
            <form method="POST" action="{{ route('animals.image.review', $animal) }}" class="mt-3">
                @csrf
                @method('PATCH')
                <input type="hidden" name="reviewed" value="{{ $animal->image_reviewed_at ? '0' : '1' }}">
                <button class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50">
                    {{ $animal->image_reviewed_at ? 'Send image back for review' : 'Mark image reviewed' }}
                </button>
            </form>
            <form method="POST" action="{{ route('animals.image.destroy', $animal) }}" class="mt-2" onsubmit="return confirm('Remove this animal image?')">
                @csrf
                @method('DELETE')
                <button class="w-full rounded-xl px-3 py-2 text-sm font-bold text-red-700 hover:bg-red-50">Remove image</button>
            </form>
        @endif
    </div>
</details>
