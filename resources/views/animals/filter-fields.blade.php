@php
    $selectFilters = [
        'kind' => [
            'label' => 'Wild or domestic',
            'options' => ['' => 'Any kind', 'wild' => 'Wild', 'domestic' => 'Domestic'],
        ],
        'scan' => [
            'label' => 'Scanner support',
            'options' => ['' => 'Any scanner status', 'scannable' => 'Scanner-detectable', 'library-only' => 'Library only'],
        ],
        'types' => [
            'label' => 'Breeds and varieties',
            'options' => ['' => 'Any', 'with' => 'Has breeds or varieties', 'without' => 'No breeds or varieties'],
        ],
        'image' => [
            'label' => 'Image',
            'options' => ['' => 'Any image status', 'with' => 'Has an image', 'without' => 'No image'],
        ],
        'source' => [
            'label' => 'Source',
            'options' => ['' => 'Any source', 'seeded' => 'Seeded animal', 'user-added' => 'User-added animal'],
        ],
        'review' => [
            'label' => 'Review',
            'options' => ['' => 'Any review status', 'reviewed' => 'Reviewed', 'unreviewed' => 'Unreviewed'],
        ],
    ];
@endphp

<div class="space-y-6">
    <div>
        <label for="{{ $prefix }}-q" class="form-label">Search</label>
        <input id="{{ $prefix }}-q" name="q" value="{{ $filters['q'] }}" placeholder="Name, scientific name, or scan label" class="form-input">
    </div>

    <fieldset>
        <legend class="form-label">Animal class</legend>
        <div class="space-y-2">
            @foreach ($classFacets as $facet)
                <label class="flex items-center justify-between gap-3 rounded-lg px-2 py-1.5 text-sm hover:bg-emerald-50">
                    <span class="flex items-center gap-2">
                        <input id="{{ $prefix }}-class-{{ $facet['value'] }}" type="checkbox" name="class[]" value="{{ $facet['value'] }}" @checked(in_array($facet['value'], $filters['classes'], true)) class="h-4 w-4 rounded border-slate-300 text-emerald-700 focus:ring-emerald-600">
                        <span>{{ $facet['label'] }}</span>
                    </span>
                    <span class="text-xs font-bold text-slate-400">{{ $facet['count'] }}</span>
                </label>
            @endforeach
        </div>
    </fieldset>

    <fieldset>
        <legend class="form-label">Conservation category</legend>
        <div class="space-y-2">
            @foreach ($statusFacets as $facet)
                <label class="flex items-center justify-between gap-3 rounded-lg px-2 py-1.5 text-sm hover:bg-emerald-50">
                    <span class="flex items-center gap-2">
                        <input id="{{ $prefix }}-status-{{ $facet['value'] }}" type="checkbox" name="status[]" value="{{ $facet['value'] }}" @checked(in_array($facet['value'], $filters['statuses'], true)) class="h-4 w-4 rounded border-slate-300 text-emerald-700 focus:ring-emerald-600">
                        <span>{{ $facet['label'] }}</span>
                    </span>
                    <span class="text-xs font-bold text-slate-400">{{ $facet['count'] }}</span>
                </label>
            @endforeach
        </div>
    </fieldset>

    @foreach ($selectFilters as $key => $select)
        <div>
            <label for="{{ $prefix }}-{{ $key }}" class="form-label">{{ $select['label'] }}</label>
            <select id="{{ $prefix }}-{{ $key }}" name="{{ $key }}" class="form-input">
                @foreach ($select['options'] as $value => $label)
                    <option value="{{ $value }}" @selected($filters[$key] === ($value === '' ? null : $value))>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    @endforeach

    <div>
        <label for="{{ $prefix }}-sort" class="form-label">Sort by</label>
        <select id="{{ $prefix }}-sort" name="sort" class="form-input">
            <option value="name" @selected($filters['sort'] === 'name')>Common name</option>
            <option value="newest" @selected($filters['sort'] === 'newest')>Newest first</option>
            <option value="status" @selected($filters['sort'] === 'status')>Conservation concern</option>
        </select>
    </div>

    <div class="flex flex-col gap-2">
        <button class="rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-800">Apply filters</button>
        <a href="{{ route('animals.index') }}" class="rounded-xl border border-slate-300 px-4 py-2.5 text-center text-sm font-bold text-slate-700 hover:bg-slate-50">Clear all</a>
    </div>
</div>
