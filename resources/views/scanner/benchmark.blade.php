@extends('layouts.public')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
    <div class="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
        <div>
            <p class="text-sm font-bold uppercase tracking-[0.2em] text-emerald-700">Internal benchmark</p>
            <h1 class="mt-2 text-4xl font-black">Recogniser benchmark</h1>
            <p class="mt-3 max-w-3xl text-slate-600">Runs CLIP, the MobileNet group fallback, and COCO-SSD on labelled photos, side by side. Accuracy shown here describes this photo set only — it is not a public accuracy claim.</p>
        </div>
        <span id="bench-status" aria-live="polite" class="rounded-full bg-slate-100 px-4 py-2 text-sm font-bold text-slate-700">Idle</span>
    </div>

    <div class="mt-8 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
            <div class="flex-1">
                <label for="bench-files" class="form-label">Labelled photos</label>
                <input id="bench-files" type="file" accept="image/*" multiple class="mt-1 block w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm">
                <p class="mt-1 text-xs text-slate-500">Pick photos, set the expected animal for each, then run. Files stay in the browser.</p>
            </div>
            <button id="bench-run" type="button" class="rounded-xl bg-brand-dark px-6 py-3 font-bold text-white hover:bg-emerald-900" disabled>Run benchmark</button>
            <div>
                <label for="bench-mode" class="form-label">Mode</label>
                <select id="bench-mode" class="mt-1 rounded-xl border border-slate-300 px-4 py-2.5 text-sm">
                    <option value="library">Animal Library</option>
                    <option value="open">Open vocabulary (Tier 1)</option>
                    <option value="out-of-scope">Out-of-scope check</option>
                </select>
            </div>
            <button id="bench-export" type="button" class="rounded-xl border border-slate-300 px-5 py-3 font-bold text-slate-700 hover:bg-slate-50" disabled>Export CSV</button>
        </div>
        <progress id="bench-progress" class="mt-5 hidden h-2 w-full overflow-hidden rounded-full" max="100" value="0"></progress>
        <p id="bench-summary" class="mt-4 hidden rounded-xl bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-900"></p>
    </div>

    <div class="mt-6 overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full min-w-[1100px] text-left text-sm">
            <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-4 py-3">Photo</th>
                    <th>Expected</th>
                    <th>CLIP top-1</th>
                    <th>CLIP top-3</th>
                    <th>MobileNet group</th>
                    <th>MobileNet raw class</th>
                    <th>COCO-SSD</th>
                    <th>CLIP + fallback</th>
                    <th>Open vocabulary</th>
                </tr>
            </thead>
            <tbody id="bench-rows" class="divide-y divide-slate-100">
                <tr><td colspan="9" class="px-4 py-10 text-center text-slate-500">Choose photos to start.</td></tr>
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@4.22.0/dist/tf.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@tensorflow-models/mobilenet@2.1.1/dist/mobilenet.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@tensorflow-models/coco-ssd@2.2.3/dist/coco-ssd.min.js"></script>
<script type="module">
    import * as Clip from '/js/clip-loader.js';
    await import('/js/scanner-logic.js');

    const Logic = window.EcoSightLogic;
    const CFG = Logic.CONFIG;
    const fileInput = document.getElementById('bench-files');
    const runButton = document.getElementById('bench-run');
    const exportButton = document.getElementById('bench-export');
    const progress = document.getElementById('bench-progress');
    const status = document.getElementById('bench-status');
    const summary = document.getElementById('bench-summary');
    const rowsBody = document.getElementById('bench-rows');
    const modeSelect = document.getElementById('bench-mode');

    let labels = null;
    let labelOptions = [];
    let groupMap = null;
    let clip = null;
    let clipEmbeddings = null;
    let mobileModel = null;
    let cocoModel = null;
    let results = [];
    let openVocabWorker = null;
    let openVocabReady = false;
    let openVocabRequestId = 0;
    const openVocabPending = new Map();

    function workerRequest(payload) {
        return new Promise((resolve, reject) => {
            if (!openVocabWorker) {
                openVocabWorker = new Worker('/js/open-vocab-worker.js');
                openVocabWorker.addEventListener('message', (event) => {
                    const pending = openVocabPending.get(event.data.id);
                    if (!pending) return;
                    openVocabPending.delete(event.data.id);
                    if (event.data.type === 'error') pending.reject(new Error(event.data.message));
                    else pending.resolve(event.data);
                });
            }
            const id = ++openVocabRequestId;
            openVocabPending.set(id, { resolve, reject });
            openVocabWorker.postMessage({ ...payload, id });
        });
    }

    async function ensureOpenVocab() {
        if (openVocabReady) return;
        await workerRequest({ type: 'load-tier1' });
        openVocabReady = true;
    }

    function openVocabHit(result, expected) {
        if (!expected) return null;
        const wanted = expected.trim().toLowerCase();

        if (wanted === 'out of scope' || wanted === 'out-of-scope' || wanted === result.outOfScope?.label?.toLowerCase()) {
            return Boolean(result.outOfScope);
        }

        const candidates = (result.openVocab?.combinedCandidates || []).slice(0, 5);
        return candidates.some((candidate) => {
            const entry = candidate.entry || {};
            return [entry.common_name, entry.group_name, entry.scientific_name]
                .filter(Boolean)
                .some((name) => String(name).trim().toLowerCase() === wanted);
        });
    }

    function setStatus(text, tone = 'neutral') {
        status.textContent = text;
        status.className = 'rounded-full px-4 py-2 text-sm font-bold ' + ({
            success: 'bg-lime-100 text-lime-800',
            warning: 'bg-amber-100 text-amber-800',
            error: 'bg-red-100 text-red-800',
            neutral: 'bg-slate-100 text-slate-700',
        }[tone] || 'bg-slate-100 text-slate-700');
    }

    function escapeHtml(value) {
        return String(value).replace(/[&<>'"]/g, (character) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'
        }[character]));
    }

    function percent(value) {
        return `${(Number(value) * 100).toFixed(1)}%`;
    }

    function optionMarkup(selected, placeholder = '— expected —') {
        return [`<option value="">${placeholder}</option>`]
            .concat(labelOptions.map((label) => `<option value="${escapeHtml(label)}"${label === selected ? ' selected' : ''}>${escapeHtml(label)}</option>`))
            .join('');
    }

    function renderPending(files) {
        const openMode = modeSelect.value !== 'library';
        rowsBody.innerHTML = files.map((file, index) => `
            <tr data-file-index="${index}">
                <td class="max-w-[240px] truncate px-4 py-3 font-bold" title="${escapeHtml(file.name)}">${escapeHtml(file.name)}</td>
                <td>${openMode
                    ? `<input data-expected="${index}" type="text" placeholder="Expected species" class="w-36 rounded-lg border-slate-300 py-1.5 text-xs">`
                    : `<select data-expected="${index}" class="rounded-lg border-slate-300 py-1.5 text-xs">${optionMarkup('')}</select>`}</td>
                <td class="ben-clip-top1 text-slate-400">—</td>
                <td class="ben-clip-top3 text-slate-400">—</td>
                <td class="ben-mobile-group text-slate-400">—</td>
                <td class="ben-mobile-raw text-slate-400">—</td>
                <td class="ben-coco text-slate-400">—</td>
                <td class="ben-combined text-slate-400">—</td>
                <td class="ben-open text-slate-400">—</td>
            </tr>
        `).join('');
    }

    async function loadLabelsAndGroups() {
        if (!labels) {
            const response = await fetch(@json(route('scanner.labels')), { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('Could not load supported labels.');
            labels = await response.json();
            labelOptions = Object.keys(labels.label_names || {}).sort();
        }
        if (!groupMap) {
            const response = await fetch(@json(asset('data/imagenet-groups.json')));
            const data = await response.json();
            groupMap = {};
            Object.entries(data.labels || {}).forEach(([className, animal]) => {
                groupMap[className.toLowerCase()] = animal;
                className.split(',').forEach((part) => {
                    const key = part.trim().toLowerCase();
                    if (key) groupMap[key] = animal;
                });
            });
        }
        return { labels, groupMap };
    }

    async function loadModels() {
        if (!clip) {
            setStatus('Loading CLIP…', 'neutral');
            clip = await Clip.loadClip({
                onProgress: (payload) => {
                    if (payload && payload.status === 'done') setStatus(`Loading CLIP… ${String(payload.file || '').split('/').pop()} ready`);
                },
            });
            const embeddings = await Clip.ensureTextEmbeddings(clip, { clipPrompts: labels.clip_prompts });
            clipEmbeddings = embeddings.embeddings;
        }
        if (!mobileModel) {
            setStatus('Loading MobileNet…', 'neutral');
            mobileModel = await window.mobilenet.load();
        }
        if (!cocoModel) {
            setStatus('Loading COCO-SSD…', 'neutral');
            cocoModel = await window.cocoSsd.load();
        }
    }

    function loadImage(file) {
        return new Promise((resolve, reject) => {
            const image = new Image();
            const url = URL.createObjectURL(file);
            image.onload = () => {
                URL.revokeObjectURL(url);
                resolve(image);
            };
            image.onerror = () => {
                URL.revokeObjectURL(url);
                reject(new Error('Unreadable image'));
            };
            image.src = url;
        });
    }

    function cocoLabelFor(predictions) {
        const best = (predictions || [])
            .filter((prediction) => (labels.coco || []).includes(prediction.class))
            .sort((a, b) => b.score - a.score)[0];
        if (best) return { label: best.class, probability: best.score };
        const top = (predictions || []).sort((a, b) => b.score - a.score)[0];
        return top ? { label: `${top.class} (unsupported)`, probability: top.score } : null;
    }

    async function classifyFile(file, expected) {
        const image = await loadImage(file);
        const startedAt = performance.now();

        const clipResult = await Clip.classifyImage(clip, image, {
            embeddings: clipEmbeddings,
            scale: CFG.CLIP_LOGIT_SCALE,
        });
        const clipDecision = Logic.decideFromClip(clipResult.animals, clipResult.distractors);

        const mobileTop = await mobileModel.classify(image, 5).catch(() => []);
        const mobileDecision = Logic.decideFromFallback(mobileTop, groupMap, {});
        const coco = cocoLabelFor(await cocoModel.detect(image).catch(() => []));

        const combined = (clipDecision && mobileDecision)
            ? Logic.applyAgreement(clipDecision, mobileDecision)
            : (clipDecision || mobileDecision);

        let openVocab = null;
        if (modeSelect.value !== 'library') {
            await ensureOpenVocab();
            openVocab = await workerRequest({ type: 'score', vector: Array.from(clipResult.vector), shortlist: 20 });
        }

        const clipTop = clipResult.animals.slice(0, 3);
        const clipTop1 = clipTop[0] || null;
        const clipHit = expected ? Boolean(clipTop1 && clipTop1.label === expected) : null;
        const clipTop3Hit = expected ? clipTop.some((entry) => entry.label === expected) : null;
        const mobileGroup = mobileDecision && mobileDecision.label ? mobileDecision : null;
        const mobileHit = expected ? Boolean(mobileGroup && mobileGroup.label === expected) : null;
        const combinedHit = expected ? Boolean(combined && combined.label === expected) : null;
        const outOfScope = (clipResult.outOfScope || [])[0] || null;
        const openHit = openVocab ? openVocabHit({ openVocab, outOfScope }, expected) : null;

        return {
            file: file.name,
            expected,
            clipTop,
            clipTop1,
            clipHit,
            clipTop3Hit,
            mobileTop,
            mobileGroup,
            mobileHit,
            coco,
            combined,
            combinedHit,
            openVocab,
            openTop: (openVocab?.combinedCandidates || []).slice(0, 3),
            outOfScope,
            openHit,
            ms: Math.round(performance.now() - startedAt),
        };
    }

    function renderResult(index, result) {
        const row = rowsBody.querySelector(`tr[data-file-index="${index}"]`);
        if (!row) return;

        const hit = (ok) => ok === null ? '' : ok ? ' · <span class="font-black text-emerald-700">hit</span>' : ' · <span class="font-bold text-red-600">miss</span>';

        row.querySelector('.ben-clip-top1').innerHTML = result.clipTop1
            ? `${escapeHtml(result.clipTop1.label)} (${percent(result.clipTop1.probability)})${hit(result.clipHit)}`
            : '—';
        row.querySelector('.ben-clip-top3').innerHTML = result.clipTop
            .map((entry) => escapeHtml(entry.label)).join(', ') || '—';
        row.querySelector('.ben-mobile-group').innerHTML = result.mobileGroup
            ? `${escapeHtml(result.mobileGroup.label)} (${percent(result.mobileGroup.confidence)})${hit(result.mobileHit)}`
            : '—';
        row.querySelector('.ben-mobile-raw').textContent = result.mobileTop[0]
            ? `${result.mobileTop[0].className} (${percent(result.mobileTop[0].probability)})`
            : '—';
        row.querySelector('.ben-coco').textContent = result.coco
            ? `${result.coco.label} (${percent(result.coco.probability)})`
            : '—';
        row.querySelector('.ben-combined').innerHTML = result.combined && result.combined.label
            ? `${escapeHtml(result.combined.label)}${hit(result.combinedHit)}`
            : '—';

        const openCell = row.querySelector('.ben-open');
        if (openCell) {
            if (!result.openVocab) {
                openCell.textContent = '—';
            } else {
                const top = result.openTop[0];
                const entry = top?.entry || {};
                const outOfScope = result.outOfScope;
                openCell.innerHTML = [
                    top ? `${escapeHtml(entry.common_name || entry.group_name || 'Unknown')} (${percent(top.probability)})${hit(result.openHit)}` : 'no match',
                    outOfScope ? `<span class="block text-xs text-slate-500">out-of-scope: ${escapeHtml(outOfScope.label)} (${percent(outOfScope.probability)})</span>` : '',
                ].join('');
            }
        }
    }

    function summarize() {
        const labelled = results.filter((result) => result.expected);
        if (labelled.length === 0) {
            summary.classList.add('hidden');
            return;
        }
        const rate = (matches) => `${Math.round((matches / labelled.length) * 1000) / 10}%`;
        const clipTop1 = labelled.filter((result) => result.clipHit).length;
        const clipTop3 = labelled.filter((result) => result.clipTop3Hit).length;
        const mobileTop1 = labelled.filter((result) => result.mobileHit).length;
        const combinedTop1 = labelled.filter((result) => result.combinedHit).length;
        const openTop1 = labelled.filter((result) => result.openHit).length;
        const avgMs = Math.round(labelled.reduce((sum, result) => sum + result.ms, 0) / labelled.length);
        const openPart = modeSelect.value === 'library'
            ? ''
            : ` · open vocabulary top-5: <strong>${rate(openTop1)}</strong>`;

        summary.innerHTML = `Labelled photos: <strong>${labelled.length}</strong> · CLIP top-1: <strong>${rate(clipTop1)}</strong> · CLIP top-3: <strong>${rate(clipTop3)}</strong> · MobileNet group top-1: <strong>${rate(mobileTop1)}</strong> · CLIP + fallback: <strong>${rate(combinedTop1)}</strong>${openPart} · average ${avgMs} ms/photo`;
        summary.classList.remove('hidden');
    }

    function csv() {
        const header = ['file', 'expected', 'clip_top1', 'clip_top1_probability', 'clip_hit', 'clip_top3', 'clip_top3_hit', 'mobilenet_group', 'mobilenet_group_probability', 'mobilenet_hit', 'mobilenet_raw_class', 'mobilenet_raw_probability', 'coco_label', 'coco_probability', 'combined_label', 'combined_hit', 'open_vocab_top5', 'open_vocab_hit', 'out_of_scope_label', 'out_of_scope_probability', 'response_ms'];
        const escape = (value) => `"${String(value ?? '').replace(/"/g, '""')}"`;
        const lines = [header.join(',')];
        results.forEach((result) => {
            lines.push([
                result.file,
                result.expected,
                result.clipTop1?.label,
                result.clipTop1?.probability,
                result.clipHit,
                result.clipTop.map((entry) => entry.label).join('|'),
                result.clipTop3Hit,
                result.mobileGroup?.label,
                result.mobileGroup?.confidence,
                result.mobileHit,
                result.mobileTop[0]?.className,
                result.mobileTop[0]?.probability,
                result.coco?.label,
                result.coco?.probability,
                result.combined?.label,
                result.combinedHit,
                result.openTop?.map((candidate) => candidate.entry?.common_name || '').join('|'),
                result.openHit,
                result.outOfScope?.label,
                result.outOfScope?.probability,
                result.ms,
            ].map(escape).join(','));
        });
        return lines.join('\n');
    }

    try {
        await loadLabelsAndGroups();
        setStatus('Ready — choose photos', 'success');
    } catch (error) {
        setStatus(error.message, 'error');
    }

    function resetPending() {
        const files = Array.from(fileInput.files || []);
        runButton.disabled = files.length === 0;
        exportButton.disabled = true;
        results = [];
        summary.classList.add('hidden');
        renderPending(files);
    }

    fileInput.addEventListener('change', resetPending);
    modeSelect.addEventListener('change', resetPending);

    runButton.addEventListener('click', async () => {
        const files = Array.from(fileInput.files || []);
        if (files.length === 0) return;
        runButton.disabled = true;
        exportButton.disabled = true;
        progress.classList.remove('hidden');
        progress.max = files.length;
        progress.value = 0;
        results = [];

        try {
            await loadModels();
        } catch (error) {
            setStatus(error.message, 'error');
            runButton.disabled = false;
            return;
        }

        for (let index = 0; index < files.length; index += 1) {
            const selected = rowsBody.querySelector(`[data-expected="${index}"]`);
            const expected = selected ? selected.value : '';
            setStatus(`Classifying ${index + 1} / ${files.length}…`);
            try {
                const result = await classifyFile(files[index], expected);
                results.push(result);
                renderResult(index, result);
            } catch (error) {
                results.push({ file: files[index].name, expected, clipTop: [], clipTop1: null, mobileTop: [], mobileGroup: null, coco: null, combined: null, ms: 0 });
                const row = rowsBody.querySelector(`tr[data-file-index="${index}"]`);
                if (row) row.querySelector('.ben-clip-top1').textContent = 'error';
            }
            progress.value = index + 1;
        }

        summarize();
        setStatus(`Done · ${files.length} photos`, 'success');
        runButton.disabled = false;
        exportButton.disabled = false;
    });

    exportButton.addEventListener('click', () => {
        if (results.length === 0) return;
        const blob = new Blob(['\uFEFF' + csv()], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = 'ecosight-benchmark.csv';
        link.click();
        URL.revokeObjectURL(url);
    });
</script>
@endpush
