@extends('layouts.public')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
    <div class="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
        <div>
            <p class="text-sm font-bold uppercase tracking-[0.2em] text-emerald-700">AI Scanner</p>
            <h1 class="mt-2 text-4xl font-black">Identify a land animal</h1>
            <p class="mt-3 max-w-3xl text-slate-600">Use your camera or upload a photo. The model runs in your browser, then EcoSight matches the recognized label to its educational record.</p>
        </div>
        <div class="flex flex-col items-start gap-2 sm:items-end">
            <span id="scanner-status" aria-live="polite" class="rounded-full bg-slate-100 px-4 py-2 text-sm font-bold text-slate-700">Loading AI model…</span>
            <p id="clip-notice" class="hidden rounded-full bg-amber-100 px-4 py-1.5 text-xs font-bold text-amber-800">The CLIP model could not load — the MobileNet fallback is being used.</p>
            <p id="mobilenet-notice" class="hidden rounded-full bg-amber-100 px-4 py-1.5 text-xs font-bold text-amber-800">The fallback model could not load — results rely on CLIP alone.</p>
        </div>
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-[1.2fr_0.8fr]">
        <section class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
            <div id="camera-panel" class="relative aspect-video overflow-hidden rounded-2xl bg-slate-950">
                <video id="camera" class="h-full w-full object-contain" autoplay playsinline muted></video>
                <img id="photo-preview" class="hidden h-full w-full object-contain" alt="Uploaded scan preview">
                <canvas id="overlay" class="pointer-events-none absolute"></canvas>
                <div id="camera-loading" class="absolute inset-0 flex items-center justify-center bg-slate-950/80 p-6 text-center text-white">
                    <div>
                        <div class="mx-auto h-10 w-10 animate-spin rounded-full border-4 border-white/20 border-t-white"></div>
                        <p id="camera-loading-text" class="mt-4 font-bold">Loading the scanner…</p>
                    </div>
                </div>
            </div>

            <div class="mt-4 flex flex-col gap-3 sm:flex-row">
                <button id="start-camera" type="button" class="rounded-xl bg-brand-dark px-5 py-3 font-bold text-white hover:bg-emerald-900">Start camera</button>
                <button id="switch-camera" type="button" class="hidden rounded-xl border border-slate-300 px-5 py-3 font-bold text-slate-700 hover:bg-slate-50">Switch camera</button>
                <button id="pause-resume" type="button" class="hidden rounded-xl border border-slate-300 px-5 py-3 font-bold text-slate-700 hover:bg-slate-50">Pause</button>
                <label class="cursor-pointer rounded-xl border border-slate-300 px-5 py-3 text-center font-bold text-slate-700 hover:bg-slate-50">
                    Upload a photo
                    <input id="photo-input" type="file" accept="image/*" class="hidden">
                </label>
                <button id="stop-camera" type="button" class="rounded-xl border border-red-200 px-5 py-3 font-bold text-red-700 hover:bg-red-50">Stop</button>
            </div>
            <p class="mt-3 text-xs leading-5 text-slate-500">Camera access requires permission and a secure context such as HTTPS or localhost. On a phone, use the rear camera when possible.</p>
        </section>

        <aside class="rounded-3xl bg-emerald-950 p-6 text-white shadow-sm">
            <p class="text-xs font-bold uppercase tracking-[0.2em] text-emerald-300">Recognition result</p>
            <div id="result-empty" class="mt-10 text-center">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-white/10 text-2xl">?</div>
                <h2 class="mt-4 text-xl font-black">No animal detected yet</h2>
                <p class="mt-2 text-sm leading-6 text-emerald-50/70">Point the camera at a supported animal or upload a clear photo.</p>
                <div id="record-missing-box" class="mt-4 hidden rounded-xl bg-white/10 p-4">
                    <p class="text-sm leading-6 text-emerald-50/80">The lookup could not finish. Retry in case the connection was intermittent.</p>
                    <button id="retry-record" type="button" class="mt-3 w-full rounded-lg bg-lime-300 px-4 py-2 text-sm font-black text-emerald-950 hover:bg-lime-200">Retry</button>
                </div>
                <div id="result-alternatives" class="mt-4 hidden space-y-2 text-left"></div>
            </div>

            <div id="result-card" class="hidden">
                <div class="mt-6 flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs uppercase tracking-wider text-emerald-300">Detected</p>
                        <h2 id="animal-name" class="mt-1 text-3xl font-black">—</h2>
                        <p id="animal-scientific" class="mt-1 text-sm italic text-emerald-100/60">—</p>
                    </div>
                    <span id="confidence-badge" class="rounded-full bg-lime-300 px-3 py-1 text-xs font-black text-emerald-950">—</span>
                </div>
                <p id="whole-image-note" class="mt-3 hidden rounded-lg bg-white/10 px-3 py-2 text-xs leading-5 text-emerald-50/80">Whole-image match — the model looks at the entire frame, so no single box is drawn around the animal.</p>
                <div class="mt-6 space-y-5">
                    <div><p class="result-label">Habitat</p><p id="animal-habitat" class="mt-1 text-sm leading-6 text-white/80">—</p></div>
                    <div><p class="result-label">Climate impact</p><p id="animal-climate" class="mt-1 text-sm leading-6 text-white/80">—</p></div>
                    <div><p class="result-label">Conservation status</p><p id="animal-status" class="mt-1 text-sm text-white/80">—</p></div>
                </div>
                <div id="variety-box" class="mt-5 hidden rounded-xl bg-white/10 p-4">
                    <label for="variety-select" class="result-label">Breed or variety <span class="font-normal normal-case tracking-normal text-emerald-200/70">(optional)</span></label>
                    <select id="variety-select" class="mt-2 w-full rounded-lg border-white/20 bg-white px-3 py-2 text-sm text-slate-800"></select>
                    <p id="variety-message" class="mt-2 text-xs leading-5 text-emerald-200"></p>
                </div>
                <div class="mt-6">
                    <p class="result-label">Actionable steps</p>
                    <div id="actions-list" class="mt-2 space-y-2"></div>
                </div>
                <button id="save-scan" type="button" class="mt-7 w-full rounded-xl bg-white px-5 py-3 font-black text-emerald-900 hover:bg-emerald-50" disabled>Save scan</button>
                <p id="save-message" class="mt-3 text-center text-xs text-emerald-200"></p>
                <div id="feedback-box" class="mt-5 hidden rounded-xl bg-white/10 p-4">
                    <p class="text-sm font-bold">Was this result correct?</p>
                    <div class="mt-3 flex gap-2">
                        <button id="feedback-yes" type="button" class="flex-1 rounded-lg bg-lime-300 px-4 py-2 text-sm font-black text-emerald-950 hover:bg-lime-200">Yes</button>
                        <button id="feedback-no" type="button" class="flex-1 rounded-lg border border-white/30 px-4 py-2 text-sm font-bold text-white hover:bg-white/10">No</button>
                    </div>
                    <div id="feedback-label-wrap" class="mt-3 hidden">
                        <label for="feedback-actual-label" class="text-xs font-bold text-emerald-100">What animal was it?</label>
                        <input id="feedback-actual-label" list="animal-labels" class="mt-1 w-full rounded-lg border-white/20 bg-white px-3 py-2 text-sm text-slate-800" placeholder="Choose an animal">
                        <datalist id="animal-labels">
                            @foreach ($labels as $label)
                                <option value="{{ $label }}"></option>
                            @endforeach
                        </datalist>
                    </div>
                    <p id="feedback-message" class="mt-2 text-xs text-emerald-200"></p>
                </div>
            </div>

            <div id="open-vocab-box" class="mt-5 hidden rounded-xl bg-white/10 p-4">
                <p class="text-xs font-bold uppercase tracking-wider text-lime-300">Possible species</p>
                <p id="open-vocab-message" class="mt-2 text-xs leading-5 text-emerald-50/80">When the library does not recognise the animal, EcoSight checks the photo against a much larger list of species. Pick the one you see to add it.</p>
                <div id="open-vocab-candidates" class="mt-3 space-y-2"></div>
                <button id="open-vocab-none" type="button" class="mt-3 hidden w-full rounded-lg border border-white/30 px-3 py-2 text-xs font-bold text-white hover:bg-white/10">None of these</button>
                <button id="open-vocab-extended" type="button" class="mt-3 w-full rounded-lg border border-white/30 px-3 py-2 text-xs font-bold text-white hover:bg-white/10">Download the extended species list</button>
                <button id="open-vocab-extended-decline" type="button" class="mt-2 w-full rounded-lg px-3 py-1 text-[11px] text-emerald-100/70 hover:text-white">No thanks, use the short list</button>
                <div id="open-vocab-progress" class="mt-3 hidden">
                    <div class="h-2 w-full overflow-hidden rounded-full bg-white/20">
                        <div id="open-vocab-progress-bar" class="h-2 w-0 rounded-full bg-lime-300"></div>
                    </div>
                    <p id="open-vocab-progress-text" class="mt-1 text-xs text-emerald-200"></p>
                </div>
            </div>

            <div id="species-box" class="mt-5 hidden rounded-xl bg-white/10 p-4">
                <p class="text-xs font-bold uppercase tracking-wider text-lime-300">Species details</p>
                <div class="mt-3 flex gap-3">
                    <img id="species-image" class="hidden h-20 w-20 rounded-lg object-cover" alt="">
                    <div>
                        <h3 id="species-name" class="text-lg font-black">—</h3>
                        <p id="species-scientific" class="text-xs italic text-emerald-100/60">—</p>
                        <p id="species-status" class="mt-1 text-xs text-emerald-100/80">—</p>
                    </div>
                </div>
                <p id="species-description" class="mt-3 text-xs leading-5 text-emerald-50/80">—</p>
                <p id="species-source" class="mt-2 text-[11px] leading-4 text-emerald-200/70"></p>
                <div id="species-rare" class="mt-3 hidden rounded-lg border border-amber-300/60 bg-amber-300/15 px-3 py-2 text-xs font-bold text-amber-100">Rare or threatened species: please check the details before adding.</div>
                <form id="species-review" class="mt-4 hidden space-y-2">
                    <p class="text-xs font-bold uppercase tracking-wider text-lime-300">Check before adding</p>
                    <input type="hidden" id="species-qid" name="qid">
                    <input type="hidden" id="species-confidence" name="confidence">
                    <input type="hidden" id="species-tier" name="tier">
                    <input type="hidden" id="species-image-token" name="image_candidate_token">
                    <input type="hidden" id="species-source-url" name="source_url">
                    <label class="block text-[11px] font-bold text-emerald-100">Common name
                        <input id="species-common-name" name="common_name" maxlength="100" class="mt-1 w-full rounded-lg border-white/20 bg-white px-3 py-2 text-sm text-slate-800" required>
                    </label>
                    <label class="block text-[11px] font-bold text-emerald-100">Scientific name
                        <input id="species-scientific-input" name="scientific_name" maxlength="150" class="mt-1 w-full rounded-lg border-white/20 bg-white px-3 py-2 text-sm text-slate-800">
                    </label>
                    <label class="block text-[11px] font-bold text-emerald-100">General name (used when adding as a type)
                        <input id="species-group-name" name="group_name" maxlength="100" class="mt-1 w-full rounded-lg border-white/20 bg-white px-3 py-2 text-sm text-slate-800">
                    </label>
                    <label class="block text-[11px] font-bold text-emerald-100">Description
                        <textarea id="species-description-input" name="description" maxlength="1200" rows="4" class="mt-1 w-full rounded-lg border-white/20 bg-white px-3 py-2 text-sm text-slate-800" required></textarea>
                    </label>
                    <label class="block text-[11px] font-bold text-emerald-100">Habitat
                        <input id="species-habitat" name="habitat" maxlength="255" class="mt-1 w-full rounded-lg border-white/20 bg-white px-3 py-2 text-sm text-slate-800">
                    </label>
                    <label class="block text-[11px] font-bold text-emerald-100">Climate impact
                        <textarea id="species-climate" name="climate_impact" maxlength="1200" rows="2" class="mt-1 w-full rounded-lg border-white/20 bg-white px-3 py-2 text-sm text-slate-800"></textarea>
                    </label>
                    <label class="block text-[11px] font-bold text-emerald-100">Conservation status
                        <select id="species-status-input" name="conservation_status" class="mt-1 w-full rounded-lg border-white/20 bg-white px-3 py-2 text-sm text-slate-800">
                            <option value="">Check IUCN Red List</option>
                            <option>Least Concern</option>
                            <option>Near Threatened</option>
                            <option>Vulnerable</option>
                            <option>Endangered</option>
                            <option>Critically Endangered</option>
                            <option>Extinct in the Wild</option>
                            <option>Extinct</option>
                            <option>Data Deficient</option>
                        </select>
                    </label>
                    <div class="space-y-2 pt-1">
                    <button id="species-add" type="button" class="w-full rounded-lg bg-lime-300 px-4 py-2 text-sm font-black text-emerald-950 hover:bg-lime-200">Add to the library</button>
                    <button id="species-add-as-type" type="button" class="hidden w-full rounded-lg border border-white/30 px-4 py-2 text-sm font-bold text-white hover:bg-white/10">Add as a type of <span id="species-add-as-type-target">—</span></button>
                </div>
                </form>
                <p id="species-message" class="mt-2 text-xs text-emerald-200"></p>
            </div>
        </aside>
    </div>

    <div id="debug-panel" class="mt-6 hidden rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-xs font-black uppercase tracking-wider text-slate-500">Debug · raw predictions and decisions</p>
        <pre id="debug-output" class="mt-3 overflow-x-auto whitespace-pre-wrap font-mono text-xs leading-5 text-slate-700"></pre>
    </div>

    <div id="scanner-help" class="mt-6 grid gap-4 md:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-white p-5"><p class="font-black">Scanning tips</p><p class="mt-2 text-sm leading-6 text-slate-600">Use good lighting, keep the animal at a close distance, and frame it fully in view. A clear, centered photo gives the most confident result.</p></div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5"><p class="font-black">How recognition works</p><p class="mt-2 text-sm leading-6 text-slate-600">CLIP compares the photo with text descriptions of every supported animal (zero-shot). When CLIP is unsure, a MobileNet fallback checks the whole image against grouped ImageNet classes such as dog breeds or big cats.</p></div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5"><p class="font-black">Supported animals ({{ $coco->count() + $mobilenet->count() }})</p><p class="mt-2 text-sm leading-6 text-slate-600">
            Land mammals and birds in the EcoSight library, from pets such as dogs, cats, and rabbits to wildlife such as elephants, bears, and big cats.
        </p></div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5"><p class="font-black">No animal / low confidence</p><p class="mt-2 text-sm leading-6 text-slate-600">Non-animal photos are rejected. Close races become a “which animal is it?” choice, and low-confidence results must be confirmed by you before they can be saved.</p></div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5"><p class="font-black">Camera denied</p><p class="mt-2 text-sm leading-6 text-slate-600">Allow camera permission in your browser settings or use the upload-photo fallback.</p></div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5"><p class="font-black">Privacy</p><p class="mt-2 text-sm leading-6 text-slate-600">The camera image is processed entirely in your browser. Nothing is uploaded until you save a scan.</p></div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@4.22.0/dist/tf.min.js"></script>
<script>
    window.ecoSightScannerConfig = {
        scanUrl: @json(route('scanner.store')),
        lookupUrl: @json(url('/scanner/animal')),
        speciesLookupUrl: @json(url('/scanner/lookup')),
        fromScanUrl: @json(route('animals.from-scan')),
        feedbackUrl: @json(route('scans.feedback')),
        eventUrl: @json(route('scanner.event')),
        labelsUrl: @json(route('scanner.labels')),
        groupsUrl: @json(asset('data/imagenet-groups.json')),
        completeUrl: @json(url('/actions')),
        csrf: @json(csrf_token()),
        debug: @json(request('debug') === '1'),
        openVocab: {
            tier2MetadataUrl: @json(asset('data/animal-vocabulary-extended.json')),
            tier2BinaryUrl: @json(asset('data/animal-vocabulary-extended.i8.bin')),
            thresholds: {
                OPEN_MIN_PROB: @json((float) config('ecosight.open_vocabulary.thresholds.open_min_prob')),
                OPEN_MARGIN: @json((float) config('ecosight.open_vocabulary.thresholds.open_margin')),
                OPEN_TOP_K: @json((int) config('ecosight.open_vocabulary.thresholds.open_top_k')),
                EXTENDED_MIN_PROB: @json((float) config('ecosight.open_vocabulary.thresholds.extended_min_prob')),
                EXTENDED_MARGIN: @json((float) config('ecosight.open_vocabulary.thresholds.extended_margin')),
                CANDIDATE_FLOOR: @json((float) config('ecosight.open_vocabulary.thresholds.candidate_floor')),
            },
        },
    };
</script>
<script src="{{ asset('js/scanner-logic.js') }}"></script>
<script src="{{ asset('js/open-vocab-core.js') }}"></script>
<script src="{{ asset('js/scanner.js') }}"></script>
@endpush
