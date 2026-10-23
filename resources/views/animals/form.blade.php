<form method="POST" action="{{ $formAction }}" class="space-y-8">
    @csrf
    @if ($formMethod !== 'POST')
        @method($formMethod)
    @endif

    <div class="grid gap-5 sm:grid-cols-2">
        <div>
            <label class="form-label" for="common_name">Common name</label>
            <input id="common_name" name="common_name" value="{{ old('common_name', $animal->common_name) }}" required class="form-input">
            @error('common_name')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="form-label" for="scientific_name">Scientific name</label>
            <input id="scientific_name" name="scientific_name" value="{{ old('scientific_name', $animal->scientific_name) }}" required class="form-input">
            @error('scientific_name')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="form-label" for="coco_label">Scan label</label>
            <input id="coco_label" name="coco_label" value="{{ old('coco_label', $animal->coco_label) }}" list="label-options" autocomplete="off" required class="form-input">
            <datalist id="label-options">
                @foreach (['bird','cat','dog','horse','sheep','cow','elephant','bear','zebra','giraffe'] as $label)
                    <option value="{{ $label }}"></option>
                @endforeach
            </datalist>
            @error('coco_label')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="form-label" for="model_source">Scan model</label>
            <select id="model_source" name="model_source" required class="form-input">
                @foreach (['coco-ssd', 'mobilenet', 'clip'] as $source)
                    <option value="{{ $source }}" @selected(old('model_source', $animal->model_source ?? 'coco-ssd') === $source)>{{ strtoupper($source) }}</option>
                @endforeach
            </select>
            @error('model_source')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <div class="sm:col-span-2">
            <label class="form-label" for="model_labels">Extra scan labels <span class="font-normal text-slate-400">(comma-separated, optional)</span></label>
            <input id="model_labels" name="model_labels" value="{{ old('model_labels', is_array($animal->model_labels) ? implode(', ', $animal->model_labels) : $animal->model_labels) }}" class="form-input" placeholder="tiger, Bengal tiger">
            @error('model_labels')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <div class="sm:col-span-2">
            <label class="form-label" for="clip_names">CLIP text prompts <span class="font-normal text-slate-400">(comma-separated, up to 10; leave blank to use the common name)</span></label>
            <input id="clip_names" name="clip_names" value="{{ old('clip_names', is_array($animal->clip_names) ? implode(', ', $animal->clip_names) : $animal->clip_names) }}" class="form-input" placeholder="a photo of an African elephant, elephant">
            @error('clip_names')<p class="form-error">{{ $message }}</p>@enderror
            @error('clip_names.*')<p class="form-error">{{ $message }}</p>@enderror
            <p class="mt-1 text-xs text-slate-500">Used for zero-shot CLIP classification. Run <code>npm run build:text-embeddings</code> after editing to refresh committed text embeddings.</p>
        </div>
        <div class="sm:col-span-2">
            <label class="flex items-center gap-3">
                <input type="hidden" name="is_scannable" value="0">
                <input id="is_scannable" type="checkbox" name="is_scannable" value="1" @checked((bool) old('is_scannable', $animal->is_scannable ?? true)) class="h-5 w-5 rounded border-slate-300 text-emerald-700 focus:ring-emerald-600">
                <span class="text-sm font-bold text-slate-700">Detectable by the scanner</span>
            </label>
            @error('is_scannable')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="form-label" for="conservation_status">Conservation status</label>
            <input id="conservation_status" name="conservation_status" value="{{ old('conservation_status', $animal->conservation_status) }}" required class="form-input">
            @error('conservation_status')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <div class="sm:col-span-2">
            <label class="form-label" for="image_url">Image URL <span class="font-normal text-slate-400">(optional)</span></label>
            <input id="image_url" type="url" name="image_url" value="{{ old('image_url', $animal->image_url) }}" class="form-input" placeholder="https://example.com/animal.jpg">
            @error('image_url')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <div class="sm:col-span-2">
            <label class="form-label" for="description">Description</label>
            <textarea id="description" name="description" rows="4" required class="form-input">{{ old('description', $animal->description) }}</textarea>
            @error('description')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="form-label" for="habitat">Habitat</label>
            <textarea id="habitat" name="habitat" rows="5" required class="form-input">{{ old('habitat', $animal->habitat) }}</textarea>
            @error('habitat')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="form-label" for="climate_impact">Climate impact</label>
            <textarea id="climate_impact" name="climate_impact" rows="5" required class="form-input">{{ old('climate_impact', $animal->climate_impact) }}</textarea>
            @error('climate_impact')<p class="form-error">{{ $message }}</p>@enderror
        </div>
    </div>

    <div>
        <div class="flex items-end justify-between gap-4">
            <div>
                <h2 class="text-xl font-black">Conservation actions</h2>
                <p class="mt-1 text-sm text-slate-500">Add at least two actions for a complete animal profile.</p>
            </div>
            <button type="button" id="add-action" class="rounded-lg border border-emerald-700 px-3 py-2 text-sm font-bold text-emerald-800">Add row</button>
        </div>

        <div id="actions-list" class="mt-5 space-y-5">
            @php
                $formActions = old('actions');
                if ($formActions === null) {
                    $formActions = $actions->map(fn ($action) => $action->toArray())->values()->all();
                    if (count($formActions) === 0) {
                        $formActions = [[], []];
                    }
                }
            @endphp
            @foreach ($formActions as $index => $action)
                @include('animals.action-row', ['index' => $index, 'action' => $action])
            @endforeach
        </div>
    </div>

    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
        <a href="{{ route('animals.index') }}" class="rounded-xl border border-slate-300 px-5 py-3 text-center font-bold text-slate-700">Cancel</a>
        <button class="rounded-xl bg-emerald-700 px-5 py-3 font-bold text-white hover:bg-emerald-800">Save animal</button>
    </div>
</form>

<template id="action-template">
    @include('animals.action-row', ['index' => '__INDEX__', 'action' => []])
</template>

@push('scripts')
<script>
    document.getElementById('add-action').addEventListener('click', function () {
        const list = document.getElementById('actions-list');
        const template = document.getElementById('action-template').innerHTML.replaceAll('__INDEX__', list.children.length);
        list.insertAdjacentHTML('beforeend', template);
    });

    document.addEventListener('click', function (event) {
        if (event.target.matches('[data-remove-action]')) {
            event.target.closest('[data-action-row]').remove();
        }
    });
</script>
@endpush
