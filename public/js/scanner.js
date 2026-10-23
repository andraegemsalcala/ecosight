(() => {
    'use strict';

    const Logic = window.EcoSightLogic;
    const CFG = Logic.CONFIG;

    const CONFIG = window.ecoSightScannerConfig;
    const MOBILENET_SCRIPT = 'https://cdn.jsdelivr.net/npm/@tensorflow-models/mobilenet@2.1.1/dist/mobilenet.min.js';
    const EVENT_DEDUPE_MS = 30000;
    const MOBILE_RESULT_CACHE_MS = CFG.MOBILENET_INTERVAL_MS;
    const FRAME_INTERVAL_MS = 180;
    const OPEN_VOCAB_INTERVAL_MS = 1500;

    const video = document.getElementById('camera');
    const image = document.getElementById('photo-preview');
    const canvas = document.getElementById('overlay');
    const context = canvas.getContext('2d');
    const cameraPanel = document.getElementById('camera-panel');
    const status = document.getElementById('scanner-status');
    const mobilenetNotice = document.getElementById('mobilenet-notice');
    const clipNotice = document.getElementById('clip-notice');
    const loading = document.getElementById('camera-loading');
    const loadingText = document.getElementById('camera-loading-text');
    const startButton = document.getElementById('start-camera');
    const switchButton = document.getElementById('switch-camera');
    const pauseButton = document.getElementById('pause-resume');
    const stopButton = document.getElementById('stop-camera');
    const photoInput = document.getElementById('photo-input');
    const resultEmpty = document.getElementById('result-empty');
    const resultAlternatives = document.getElementById('result-alternatives');
    const resultCard = document.getElementById('result-card');
    const saveButton = document.getElementById('save-scan');
    const saveMessage = document.getElementById('save-message');
    const feedbackBox = document.getElementById('feedback-box');
    const feedbackYesButton = document.getElementById('feedback-yes');
    const feedbackNoButton = document.getElementById('feedback-no');
    const feedbackLabelWrap = document.getElementById('feedback-label-wrap');
    const feedbackLabelInput = document.getElementById('feedback-actual-label');
    const feedbackMessage = document.getElementById('feedback-message');
    const recordMissingBox = document.getElementById('record-missing-box');
    const retryRecordButton = document.getElementById('retry-record');
    const wholeImageNote = document.getElementById('whole-image-note');
    const varietyBox = document.getElementById('variety-box');
    const varietySelect = document.getElementById('variety-select');
    const varietyMessage = document.getElementById('variety-message');
    const debugPanel = document.getElementById('debug-panel');
    const debugOutput = document.getElementById('debug-output');

    const openVocabBox = document.getElementById('open-vocab-box');
    const openVocabMessage = document.getElementById('open-vocab-message');
    const openVocabCandidates = document.getElementById('open-vocab-candidates');
    const openVocabExtended = document.getElementById('open-vocab-extended');
    const openVocabProgress = document.getElementById('open-vocab-progress');
    const openVocabProgressBar = document.getElementById('open-vocab-progress-bar');
    const openVocabProgressText = document.getElementById('open-vocab-progress-text');
    const speciesBox = document.getElementById('species-box');
    const speciesName = document.getElementById('species-name');
    const speciesScientific = document.getElementById('species-scientific');
    const speciesDescription = document.getElementById('species-description');
    const speciesImage = document.getElementById('species-image');
    const speciesStatus = document.getElementById('species-status');
    const speciesSource = document.getElementById('species-source');
    const speciesAdd = document.getElementById('species-add');
    const speciesAddAsType = document.getElementById('species-add-as-type');
    const speciesAddAsTypeTarget = document.getElementById('species-add-as-type-target');
    const speciesMessage = document.getElementById('species-message');
    const speciesReview = document.getElementById('species-review');
    const speciesRare = document.getElementById('species-rare');
    const speciesQidInput = document.getElementById('species-qid');
    const speciesConfidenceInput = document.getElementById('species-confidence');
    const speciesTierInput = document.getElementById('species-tier');
    const speciesImageTokenInput = document.getElementById('species-image-token');
    const speciesSourceInput = document.getElementById('species-source-url');
    const speciesCommonNameInput = document.getElementById('species-common-name');
    const speciesScientificInput = document.getElementById('species-scientific-input');
    const speciesGroupNameInput = document.getElementById('species-group-name');
    const speciesDescriptionInput = document.getElementById('species-description-input');
    const speciesHabitatInput = document.getElementById('species-habitat');
    const speciesClimateInput = document.getElementById('species-climate');
    const speciesStatusInput = document.getElementById('species-status-input');
    const openVocabNone = document.getElementById('open-vocab-none');
    const openVocabExtendedDecline = document.getElementById('open-vocab-extended-decline');

    let clipModule = null;
    let clipResources = null;
    let clipEmbeddings = null;
    let clipState = 'idle';
    let clipLoadedFiles = 0;
    let clipStability = null;
    let lastClipVector = null;
    let lastClipDistractors = null;
    let lastClipOutOfScope = null;
    let supportedClipPrompts = null;

    let openVocabWorker = null;
    let openVocabTier2Loaded = false;
    let openVocabRequestId = 0;
    let openVocabBusy = false;
    let lastOpenVocabAt = 0;
    let lastOpenVocabTier2 = false;
    let currentSpecies = null;
    let libraryAnimals = [];
    const openVocabPending = new Map();
    const openVocabEntries = {};

    let mobileModel = null;
    let mobileScriptLoading = false;
    let mobileUnavailable = false;
    let mobileTopCache = null;
    let mobileTopCacheAt = 0;

    let groupsMap = null;
    let groupsPromise = null;

    let stream = null;
    let animationFrame = null;
    let detectionRunning = false;
    let recognitionRunning = false;
    let lastDetectionTime = 0;
    let lastClipAt = 0;
    let lastResultAt = 0;
    let lastDecision = null;

    let currentResult = null;
    let currentAnimal = null;
    let currentActions = [];
    let currentVariety = null;
    let retryCandidate = null;
    let lastLoadedLabel = null;
    let currentScanId = null;
    let cameraFacing = 'environment';
    let paused = false;
    let feedbackSubmitted = false;
    let overlaySource = null;

    const labelMap = {};
    const labelNames = {};
    const varietiesByLabel = {};
    const supportedCoco = new Set();
    const supportedMobile = new Set();
    const minConfidences = {
        'coco-ssd': CFG.MIN_CONFIDENCE_COCO,
        mobilenet: CFG.MIN_CONFIDENCE_MOBILENET,
        clip: CFG.MIN_CONFIDENCE_CLIP,
    };
    const completedActionIds = new Set();
    const lastEventSent = new Map();

    function emit(name, detail = {}) {
        document.dispatchEvent(new CustomEvent(name, { detail }));
    }

    function friendlyLabel(label) {
        return labelNames[label] || label;
    }

    function setStatus(text, type = 'neutral') {
        status.textContent = text;
        status.className = 'rounded-full px-4 py-2 text-sm font-bold ' + ({
            success: 'bg-lime-100 text-lime-800',
            warning: 'bg-amber-100 text-amber-800',
            error: 'bg-red-100 text-red-800',
            neutral: 'bg-slate-100 text-slate-700',
        }[type] || 'bg-slate-100 text-slate-700');
    }

    function showLoading(text) {
        loadingText.textContent = text;
        loading.classList.remove('hidden');
    }

    function hideLoading() {
        loading.classList.add('hidden');
    }

    function clearOverlay() {
        context.clearRect(0, 0, canvas.width, canvas.height);
    }

    /**
     * Keep the canvas exactly on the visible image area. The video/photo are
     * object-contain inside the panel, so the drawn content is letterboxed;
     * we size and position the canvas to that same content rect so overlay
     * coordinates (source pixels) map 1:1 onto what the user sees.
     */
    function alignOverlay() {
        if (!overlaySource) return;
        const width = overlaySource.videoWidth || overlaySource.naturalWidth || overlaySource.width;
        const height = overlaySource.videoHeight || overlaySource.naturalHeight || overlaySource.height;
        if (!width || !height || !cameraPanel) return;
        const panelWidth = cameraPanel.clientWidth;
        const panelHeight = cameraPanel.clientHeight;
        const scale = Math.min(panelWidth / width, panelHeight / height);
        const displayWidth = width * scale;
        const displayHeight = height * scale;
        canvas.style.left = `${(panelWidth - displayWidth) / 2}px`;
        canvas.style.top = `${(panelHeight - displayHeight) / 2}px`;
        canvas.style.width = `${displayWidth}px`;
        canvas.style.height = `${displayHeight}px`;
        canvas.style.visibility = 'visible';
    }

    function sizeCanvas(source) {
        const width = source.videoWidth || source.naturalWidth || source.width;
        const height = source.videoHeight || source.naturalHeight || source.height;
        if (!width || !height) return false;
        overlaySource = source;
        if (canvas.width !== width || canvas.height !== height) {
            canvas.width = width;
            canvas.height = height;
        }
        alignOverlay();
        return true;
    }

    function sendEvent(type, payload = {}) {
        if (!CONFIG.eventUrl) return;
        const reason = payload.reason || '';
        const key = `${type}|${reason}`;
        const now = Date.now();
        if (now - (lastEventSent.get(key) || 0) < EVENT_DEDUPE_MS) return;
        lastEventSent.set(key, now);

        const body = { type };
        if (reason) body.reason = String(reason).slice(0, 100);
        if (payload.responseTime) body.response_time_ms = Math.round(payload.responseTime);
        if (payload.modelLoadMs) body.model_load_ms = Math.round(payload.modelLoadMs);
        if (payload.cameraStartMs) body.camera_start_ms = Math.round(payload.cameraStartMs);

        fetch(CONFIG.eventUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': CONFIG.csrf,
            },
            body: JSON.stringify(body),
        }).catch(() => {});
    }

    function showMobileNetNotice() {
        if (mobileUnavailable) return;
        mobileUnavailable = true;
        if (mobilenetNotice) mobilenetNotice.classList.remove('hidden');
        sendEvent('detection-failed', { reason: 'mobilenet-load-failed' });
    }

    function showClipNotice(reason = 'clip-load-failed') {
        if (clipNotice) clipNotice.classList.remove('hidden');
        sendEvent('detection-failed', { reason });
    }

    async function loadSupportedLabels() {
        const response = await fetch(CONFIG.labelsUrl, { headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error('Unable to load supported animal labels.');
        const data = await response.json();
        supportedCoco.clear();
        supportedMobile.clear();
        Object.keys(labelMap).forEach((key) => delete labelMap[key]);
        Object.keys(labelNames).forEach((key) => delete labelNames[key]);
        Object.keys(varietiesByLabel).forEach((key) => delete varietiesByLabel[key]);
        (data.coco || []).forEach((label) => supportedCoco.add(label));
        (data.mobilenet || []).forEach((label) => supportedMobile.add(label));
        Object.assign(labelMap, data.mobilenet_map || {});
        Object.assign(labelNames, data.label_names || {});
        Object.assign(varietiesByLabel, data.varieties || {});
        Object.assign(minConfidences, data.min_confidences || {});
        supportedClipPrompts = data.clip_prompts || null;
        libraryAnimals = data.library || [];
    }

    function shortFileName(file) {
        return String(file || '').split('/').pop() || 'model';
    }

    function handleClipProgress(payload) {
        if (!payload) return;
        if (payload.status === 'progress' && payload.total) {
            const percent = Math.min(100, Math.round((payload.loaded / payload.total) * 100));
            showLoading(`Loading CLIP model… ${percent}% (${shortFileName(payload.file)})`);
        } else if (payload.status === 'done') {
            clipLoadedFiles += 1;
            showLoading(`Loading CLIP model… ${shortFileName(payload.file)} ready`);
        } else if (payload.status === 'initiate' && payload.file) {
            showLoading(`Loading CLIP model… ${shortFileName(payload.file)}`);
        }
    }

    /**
     * Load the CLIP vision encoder and text embeddings. Text embeddings are
     * reused from /data unless the labels payload proves they are stale, in
     * which case the text encoder is loaded and only text vectors recompute.
     */
    async function loadClipStack() {
        if (clipState === 'ready' || clipState === 'loading') return clipState;
        clipState = 'loading';
        try {
            clipModule = await import('/js/clip-loader.js');
            clipResources = await clipModule.loadClip({ onProgress: handleClipProgress });
            const result = await clipModule.ensureTextEmbeddings(
                clipResources,
                { clipPrompts: supportedClipPrompts },
                { onProgress: handleClipProgress }
            );
            clipEmbeddings = result.embeddings;
            clipState = 'ready';
            if (result.recomputed) {
                sendEvent('model-loaded', { reason: 'clip-text-recomputed' });
            }
        } catch (error) {
            clipState = 'unavailable';
            clipModule = null;
            clipResources = null;
            clipEmbeddings = null;
            showClipNotice();
        }
        return clipState;
    }

    /**
     * Recompute the CLIP text vectors after the library changed, so an animal
     * that was just added can be recognised in the same session.
     */
    async function refreshClipEmbeddings() {
        if (! clipModule || ! clipResources || ! supportedClipPrompts) return;
        try {
            const result = await clipModule.ensureTextEmbeddings(
                clipResources,
                { clipPrompts: supportedClipPrompts },
                { onProgress: handleClipProgress }
            );
            clipEmbeddings = result.embeddings;
            if (result.recomputed) {
                sendEvent('model-loaded', { reason: 'clip-text-recomputed' });
            }
        } catch (error) {
            showClipNotice();
        }
    }

    async function ensureMobileModel() {
        if (mobileModel) return mobileModel;
        if (mobileUnavailable) return null;
        if (window.mobilenet && !mobileModel) {
            mobileModel = await window.mobilenet.load();
            return mobileModel;
        }
        if (mobileScriptLoading) return null;
        mobileScriptLoading = true;
        try {
            await new Promise((resolve, reject) => {
                const script = document.createElement('script');
                script.src = MOBILENET_SCRIPT;
                script.onload = resolve;
                script.onerror = reject;
                document.head.appendChild(script);
            });
            mobileModel = await window.mobilenet.load();
            return mobileModel;
        } finally {
            mobileScriptLoading = false;
        }
    }

    /**
     * Whole-image MobileNet top-5, throttled to one real model run per
     * MOBILENET_INTERVAL_MS. Returns the cached array within the window, or
     * null when the model is unavailable.
     */
    async function classifyWholeImage(source) {
        try {
            const model = await ensureMobileModel();
            if (!model) {
                showMobileNetNotice();
                return null;
            }
            const now = Date.now();
            if (mobileTopCache && now - mobileTopCacheAt < MOBILE_RESULT_CACHE_MS) {
                return mobileTopCache;
            }
            const top = await model.classify(source, 5);
            mobileTopCache = (top || []).map((entry) => ({
                className: entry.className,
                probability: entry.probability,
            }));
            mobileTopCacheAt = now;
            return mobileTopCache;
        } catch (error) {
            showMobileNetNotice();
            return null;
        }
    }

    /** Load ImageNet class -> library animal groups (breeds collapse). */
    function ensureGroups() {
        if (groupsMap) return Promise.resolve(groupsMap);
        if (groupsPromise) return groupsPromise;
        groupsPromise = fetch(CONFIG.groupsUrl, { headers: { Accept: 'application/json' } })
            .then((response) => (response.ok ? response.json() : null))
            .then((data) => {
                if (!data || !data.labels) return null;
                const map = {};
                Object.entries(data.labels).forEach(([className, animal]) => {
                    map[className.toLowerCase()] = animal;
                    className.split(',').forEach((part) => {
                        const key = part.trim().toLowerCase();
                        if (key) map[key] = animal;
                    });
                });
                groupsMap = map;
                return map;
            })
            .catch(() => null);
        return groupsPromise;
    }

    async function runFallbackDecision(source) {
        const top = await classifyWholeImage(source);
        if (!top) return null;
        const map = await ensureGroups();
        if (!map) return null;
        const width = source.videoWidth || source.naturalWidth || source.width;
        const height = source.videoHeight || source.naturalHeight || source.height;
        return Logic.decideFromFallback(top, map, { width, height });
    }

    /**
     * Crop the whole frame or a centred portion of it. The scanner scores the
     * full frame and 70% / 50% centre crops; a COCO box crop is added when a
     * box is available. Missing views are re-normalised by the combiner.
     */
    function captureView(source, ratio) {
        const width = source.videoWidth || source.naturalWidth || source.width;
        const height = source.videoHeight || source.naturalHeight || source.height;
        const cropWidth = Math.max(1, Math.round(width * ratio));
        const cropHeight = Math.max(1, Math.round(height * ratio));
        const sourceX = Math.round((width - cropWidth) / 2);
        const sourceY = Math.round((height - cropHeight) / 2);
        const crop = document.createElement('canvas');
        crop.width = cropWidth;
        crop.height = cropHeight;
        crop.getContext('2d').drawImage(source, sourceX, sourceY, cropWidth, cropHeight, 0, 0, cropWidth, cropHeight);
        return crop;
    }

    async function runClip(source) {
        const views = [captureView(source, 1), captureView(source, 0.7), captureView(source, 0.5)];
        const animalViews = [];
        const distractorViews = [];
        const outOfScopeViews = [];
        let vector = null;

        for (const view of views) {
            const result = await clipModule.classifyImage(clipResources, view, {
                embeddings: clipEmbeddings,
                scale: CFG.CLIP_LOGIT_SCALE,
            });
            animalViews.push(result.animals);
            distractorViews.push(result.distractors);
            outOfScopeViews.push(result.outOfScope || []);
            if (!vector) vector = result.vector;
        }

        return {
            animals: Logic.combineClipViews(animalViews, CFG.CLIP_WEIGHTS),
            distractors: Logic.combineClipViews(distractorViews, CFG.CLIP_WEIGHTS),
            outOfScope: Logic.combineClipViews(outOfScopeViews, CFG.CLIP_WEIGHTS),
            vector,
        };
    }
    function buildVariety(clipResult, animalLabel) {
        const choices = varietiesByLabel[animalLabel] || [];
        if (!clipResult || !clipModule || clipState !== 'ready' || choices.length === 0) return null;

        const scores = clipModule.scoreVarieties(clipResult.vector, clipEmbeddings, animalLabel, CFG.CLIP_LOGIT_SCALE);
        if (!scores.length) return null;

        const scoreMap = {};
        scores.forEach((entry) => { scoreMap[entry.label] = entry.probability; });

        return { choices, decision: Logic.decideVariety(scores), scoreMap };
    }

    function topTwo(decision) {
        const candidates = decision.candidates || [];
        const runnerUp = candidates[1];
        if (!runnerUp) return null;
        return { label: runnerUp.label, confidence: runnerUp.probability };
    }

    function debug(payload) {
        if (!debugOutput) return;
        debugOutput.textContent = JSON.stringify(payload, null, 2);
        if (debugPanel) debugPanel.classList.remove('hidden');
    }

    function resetFeedback() {
        currentScanId = null;
        feedbackSubmitted = false;
        feedbackBox.classList.add('hidden');
        feedbackLabelWrap.classList.add('hidden');
        feedbackMessage.textContent = '';
        feedbackYesButton.disabled = false;
        feedbackNoButton.disabled = false;
    }

    function hideRecordMissing() {
        if (recordMissingBox) recordMissingBox.classList.add('hidden');
    }

    function setEmptyResult(title = 'No animal detected yet', message = 'Point the camera at a supported animal or upload a clear photo.', options = {}) {
        resultEmpty.classList.remove('hidden');
        resultCard.classList.add('hidden');
        resultAlternatives.classList.add('hidden');
        resultAlternatives.innerHTML = '';
        resultEmpty.querySelector('h2').textContent = title;
        resultEmpty.querySelector('p').textContent = message;
        saveButton.disabled = true;
        retryCandidate = null;
        currentAnimal = null;
        currentActions = [];
        lastLoadedLabel = null;
        hideRecordMissing();
        resetFeedback();
        if (!options.keepResult) currentResult = null;
    }

    function setAlternatives(items, options = {}) {
        if (!items || items.length === 0) {
            resultAlternatives.classList.add('hidden');
            resultAlternatives.innerHTML = '';
            return;
        }
        resultAlternatives.classList.remove('hidden');
        resultAlternatives.innerHTML = `
            <p class="text-xs font-bold uppercase tracking-wider text-emerald-300">${escapeHtml(options.title || 'Closest matches')}</p>
            ${items.map((item) => options.clickable
                ? `<button type="button" data-candidate-label="${escapeHtml(item.raw || item.label)}" class="flex w-full items-center justify-between rounded-lg bg-white/10 px-3 py-2 text-left text-sm text-white hover:bg-white/20">
                        <span>${escapeHtml(item.label)}</span>
                        <span class="font-black text-lime-300">${(item.probability * 100).toFixed(0)}%</span>
                   </button>`
                : `<div class="flex items-center justify-between rounded-lg bg-white/10 px-3 py-2 text-sm text-white">
                        <span>${escapeHtml(item.label)}</span>
                        <span class="font-black text-lime-300">${(item.probability * 100).toFixed(0)}%</span>
                   </div>`).join('')}
            ${options.clickable ? `<button type="button" id="dismiss-alternatives" class="mt-1 w-full rounded-lg border border-white/20 px-3 py-2 text-xs font-bold text-white/70 hover:bg-white/10">None of these</button>` : ''}
        `;
    }

    function showNotInLibrary(decision) {
        setEmptyResult(
            'Not in the library yet',
            `This looks like ${decision.className}, which is not in the EcoSight library yet.`
        );
        setAlternatives([{ label: decision.className, probability: decision.probability }]);
    }

    function showDistractor(decision) {
        setEmptyResult(
            'No animal in view',
            `EcoSight sees ${friendlyLabel(decision.label)} (${(decision.confidence * 100).toFixed(0)}%), not a supported animal. Try pointing the camera at the animal.`
        );
    }

    /**
     * The record lookup failed. Keep the winning result so Retry can render
     * it when the lookup succeeds, and tell 404s apart from unreachable-server
     * errors.
     */
    function showRecordFailure(result, kind) {
        lastLoadedLabel = null;
        const label = result.label;

        if (kind === 'missing') {
            setStatus('Animal record unavailable', 'error');
            setEmptyResult(
                'Animal record unavailable',
                `EcoSight recognized ${friendlyLabel(label)} but has no record for it. This is a data problem, not a scan problem.`,
                { keepResult: true }
            );
            retryCandidate = result;
            emit('record-missing', { label });
            sendEvent('record-missing', { reason: label });
            return;
        }

        setStatus('Could not reach EcoSight', 'error');
        setEmptyResult(
            'Could not reach EcoSight',
            'The server could not be reached while looking up this record. Check your connection and try again.',
            { keepResult: true }
        );
        retryCandidate = result;
        if (recordMissingBox) recordMissingBox.classList.remove('hidden');
        emit('detection-failed', { reason: 'lookup-failed', label });
        sendEvent('detection-failed', { reason: 'lookup-failed' });
    }

    async function loadAnimal(result) {
        const label = result.label;
        let response;
        try {
            response = await fetch(`${CONFIG.lookupUrl}/${encodeURIComponent(label)}`, {
                headers: { Accept: 'application/json' },
            });
        } catch (error) {
            showRecordFailure(result, 'network');
            return;
        }

        if (response.status === 404) {
            showRecordFailure(result, 'missing');
            return;
        }
        if (!response.ok) {
            showRecordFailure(result, 'server');
            return;
        }

        let data;
        try {
            data = await response.json();
        } catch (error) {
            showRecordFailure(result, 'server');
            return;
        }

        currentAnimal = data.animal;
        currentActions = data.conservation_actions || [];
        completedActionIds.clear();
        (data.completed_action_ids || []).forEach((id) => completedActionIds.add(Number(id)));
        renderAnimal(data.animal, currentActions);

        if (! currentResult) return;

        if (currentResult.needsConfirmation) {
            setStatus(`${data.animal.common_name} — please confirm`, 'warning');
        } else {
            setStatus(`${data.animal.common_name} detected`, 'success');
        }
    }

    function renderVariety() {
        if (!varietyBox) return;
        if (!currentVariety || !currentVariety.choices.length || !currentResult) {
            varietyBox.classList.add('hidden');
            currentVariety = null;
            if (currentResult) currentResult.variety = null;
            return;
        }

        const decision = currentVariety.decision;
        varietyBox.classList.remove('hidden');
        const options = currentVariety.choices.map((choice) => {
            const label = choice.label;
            return `<option value="${escapeHtml(label)}">${escapeHtml(label)}</option>`;
        });
        varietySelect.innerHTML = ['<option value="">Not sure</option>'].concat(options).join('');

        if (decision.status === 'matched') {
            varietySelect.value = decision.label;
            varietyMessage.textContent = `Best match: ${decision.label} (${(decision.confidence * 100).toFixed(0)}%). Keep it or choose another.`;
            currentResult.variety = { label: decision.label, confidence: decision.confidence, confirmed: false };
        } else {
            varietySelect.value = '';
            varietyMessage.textContent = decision.best
                ? `Best guess: ${decision.best} (${(decision.confidence * 100).toFixed(0)}%) — too close to call, choose if you know it.`
                : 'Optional: choose the breed or variety if you know it.';
            currentResult.variety = null;
        }
    }

    function renderAnimal(animal, actions) {
        if (! currentResult) return;

        resultEmpty.classList.add('hidden');
        resultCard.classList.remove('hidden');
        document.getElementById('animal-name').textContent = animal.common_name;
        document.getElementById('animal-scientific').textContent = animal.scientific_name;
        document.getElementById('animal-habitat').textContent = animal.habitat;
        document.getElementById('animal-climate').textContent = animal.climate_impact;
        document.getElementById('animal-status').textContent = animal.conservation_status;
        document.getElementById('confidence-badge').textContent = `${(currentResult.confidence * 100).toFixed(1)}% confidence`;
        if (wholeImageNote) wholeImageNote.classList.toggle('hidden', !currentResult.wholeImage);
        document.getElementById('actions-list').innerHTML = actions.map((action) => {
            const done = completedActionIds.has(Number(action.id));
            const actionUrl = `${CONFIG.completeUrl}/${action.id}/complete`;
            const control = done
                ? `<div class="mt-3 flex items-center justify-between">
                        <span class="text-xs font-black uppercase tracking-wider text-lime-300">Done</span>
                        <form method="POST" action="${actionUrl}">
                            <input type="hidden" name="_token" value="${CONFIG.csrf}">
                            <input type="hidden" name="_method" value="DELETE">
                            <button class="rounded-lg border border-white/30 px-3 py-1.5 text-xs font-bold text-white hover:bg-white/10">Undo</button>
                        </form>
                   </div>`
                : `<form method="POST" action="${actionUrl}" class="mt-3">
                        <input type="hidden" name="_token" value="${CONFIG.csrf}">
                        <button class="rounded-lg bg-lime-300 px-4 py-1.5 text-xs font-black text-emerald-950 hover:bg-lime-200">Mark as done</button>
                   </form>`;
            return `
                <div class="rounded-xl bg-white/10 p-3">
                    <div class="flex items-start justify-between gap-3">
                        <span class="text-sm font-bold">${escapeHtml(action.title)}</span>
                        <span class="text-[10px] font-bold uppercase text-lime-300">${escapeHtml(action.difficulty)}</span>
                    </div>
                    <p class="mt-1 text-xs leading-5 text-white/70">${escapeHtml(action.description)}</p>
                    ${control}
                </div>
            `;
        }).join('');
        saveButton.textContent = currentResult.needsConfirmation ? 'Confirm & save' : 'Save scan';
        saveButton.disabled = false;
        saveMessage.textContent = '';
        if (openVocabBox) openVocabBox.classList.add('hidden');
        hideRecordMissing();
        resetFeedback();
        renderVariety();
    }

    function escapeHtml(value) {
        return String(value).replace(/[&<>'"]/g, (character) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'
        }[character]));
    }

    /**
     * The open-vocabulary matcher.
     *
     * The library only knows a few dozen animals, so a photo of a European
     * robin or a bombardier beetle would be rejected. The open-vocabulary
     * worker scores the same photo against a vocabulary of thousands of
     * species, and the user can then add the one they see to the library.
     */

    function ensureOpenVocabWorker() {
        if (openVocabWorker) return openVocabWorker;

        openVocabWorker = new Worker('/js/open-vocab-worker.js');

        openVocabWorker.addEventListener('message', (event) => {
            const data = event.data || {};
            const pending = openVocabPending.get(data.id);
            if (! pending) return;
            openVocabPending.delete(data.id);
            if (data.type === 'error') {
                pending.reject(new Error(data.message || 'Species matching failed.'));
                return;
            }
            pending.resolve(data);
        });

        openVocabWorker.addEventListener('error', (event) => {
            openVocabPending.forEach((pending) => {
                pending.reject(new Error(event.message || 'The species matcher stopped.'));
            });
            openVocabPending.clear();
            openVocabWorker = null;
        });

        return openVocabWorker;
    }

    function openVocabRequest(type, payload = {}) {
        const worker = ensureOpenVocabWorker();
        return new Promise((resolve, reject) => {
            const id = ++openVocabRequestId;
            openVocabPending.set(id, { resolve, reject });
            worker.postMessage(Object.assign({ id, type }, payload));
        });
    }

    /**
     * Load the extended species list, downloading it the first time.
     *
     * The download is verified against the checksum in the metadata before it
     * is cached, so a damaged file is never scored.
     */
    async function ensureOpenVocabTier2() {
        if (openVocabTier2Loaded) return true;

        const store = await import('/js/open-vocab-store.js');
        const cached = await store.getCachedVocabulary('tier2');

        let metadata = null;
        let buffer = null;

        if (cached) {
            metadata = cached.metadata;
            buffer = cached.buffer;
        } else {
            const result = await store.downloadVocabulary(
                CONFIG.openVocab.tier2MetadataUrl,
                CONFIG.openVocab.tier2BinaryUrl,
                (loaded, total) => {
                    const percent = total ? Math.min(100, Math.round((loaded / total) * 100)) : 0;
                    if (openVocabProgressBar) openVocabProgressBar.style.width = `${percent}%`;
                    if (openVocabProgressText) {
                        openVocabProgressText.textContent = `Downloading the extended species list… ${percent}%`;
                    }
                }
            );
            metadata = result.metadata;
            buffer = result.buffer;
            await store.cacheVocabulary('tier2', metadata, buffer);
            sendEvent('extended-downloaded', { reason: 'tier2' });
        }

        await openVocabRequest('set-tier2', { metadata, buffer });
        openVocabTier2Loaded = true;
        sendEvent('extended-list-loaded', { reason: 'tier2' });

        return true;
    }

    async function runOpenVocab(vector, distractors, outOfScope) {
        const result = await openVocabRequest('score', { vector, shortlist: 20 });
        const openVocab = window.EcoSightOpenVocab;

        const decision = openVocab.decideOpenVocab({
            tier1: result.tier1Candidates || [],
            tier2: result.tier2Candidates || [],
            combined: result.combinedCandidates || [],
            outOfScope: outOfScope || [],
        }, CONFIG.openVocab.thresholds);

        return {
            decision,
            tier1: result.tier1Candidates || [],
            tier2: result.tier2Candidates || [],
        };
    }

    /**
     * Score the photo against the species vocabulary when the library did not
     * recognise it. The result is shown as suggestions the user can pick from.
     */
    async function maybeRunOpenVocab(clipResult) {
        if (! clipResult || ! clipResult.vector || ! openVocabBox) return;
        if (openVocabBusy) return;

        const now = Date.now();
        if (now - lastOpenVocabAt < OPEN_VOCAB_INTERVAL_MS) return;
        lastOpenVocabAt = now;
        openVocabBusy = true;

        try {
            const result = await runOpenVocab(clipResult.vector, clipResult.distractors, clipResult.outOfScope);
            renderOpenVocab(result);
        } catch (error) {
            sendEvent('detection-failed', { reason: 'open-vocab-failed' });
        } finally {
            openVocabBusy = false;
        }
    }

    function renderOpenVocab(result) {
        const { decision, tier2 } = result;
        const candidates = Array.isArray(decision.candidates) ? decision.candidates : [];
        lastOpenVocabTier2 = tier2.length > 0;

        if (decision.status === 'out-of-scope') {
            openVocabBox.classList.add('hidden');
            setStatus('Outside EcoSight\'s scope', 'neutral');
            setEmptyResult(
                'Outside EcoSight\'s scope',
                `That looks like a ${friendlyLabel(decision.label)}. EcoSight covers land animals, so it cannot be added.`
            );
            sendEvent('out-of-scope-animal', { reason: decision.label });
            return;
        }

        openVocabBox.classList.remove('hidden');
        openVocabMessage.textContent = decision.status === 'matched'
            ? 'EcoSight thinks this is one of these species. It is not in the library yet, so you can add it.'
            : candidates.length
                ? 'These are the closest species EcoSight found. Pick the one you see to add it to the library.'
                : 'No match found in the short list. Try the extended species list or take another photo.';

        Object.keys(openVocabEntries).forEach((key) => delete openVocabEntries[key]);
        openVocabCandidates.innerHTML = candidates.map((candidate) => {
            const entry = candidate.entry || {};
            const qid = entry.qid || '';
            if (qid) {
                openVocabEntries[qid] = Object.assign({}, entry, {
                    probability: candidate.probability,
                    tier: candidate.tier || 1,
                });
            }
            const name = window.EcoSightOpenVocab.displayName(entry);
            const scientific = entry.scientific_name ? `<span class="block text-[11px] italic text-emerald-100/60">${escapeHtml(entry.scientific_name)}</span>` : '';
            const meta = [entry.group_label, entry.iucn_status].filter(Boolean).join(' · ');
            return `<button type="button" data-species-qid="${escapeHtml(qid)}" data-species-tier="${candidate.tier || 1}" class="flex w-full items-center justify-between gap-3 rounded-lg bg-white/10 px-3 py-2 text-left text-sm text-white hover:bg-white/20">
                        <span class="min-w-0"><span class="block font-bold">${escapeHtml(name)}</span>${scientific}<span class="block text-[11px] text-emerald-200/70">${escapeHtml(meta)}</span></span>
                        <span class="shrink-0 font-black text-lime-300">${candidate.tier === 2 ? 'Extended' : (candidate.probability * 100).toFixed(0) + '%'}</span>
                   </button>`;
        }).join('');

        const hasCandidates = candidates.length > 0;
        openVocabNone.classList.toggle('hidden', ! hasCandidates);
        openVocabExtended.classList.toggle('hidden', lastOpenVocabTier2);
        openVocabExtendedDecline.classList.toggle('hidden', ! hasCandidates || lastOpenVocabTier2);

        if (! lastOpenVocabTier2 && ! hasCandidates) {
            sendEvent('extended-offered', { reason: decision.rule });
        }

        sendEvent('open-vocab-run', { reason: decision.rule });
    }

    /**
     * Show the public information of a species and the ways to add it.
     */
    async function showSpecies(qid, confidence, tier) {
        if (! speciesBox) return;

        speciesBox.classList.remove('hidden');
        speciesReview.classList.add('hidden');
        speciesRare.classList.add('hidden');
        speciesImage.removeAttribute('src');
        speciesImage.classList.add('hidden');
        speciesImageTokenInput.value = '';
        speciesMessage.textContent = 'Loading public information...';
        currentSpecies = { qid, confidence: confidence || 0, data: null, tier: tier || 1 };

        try {
            const response = await fetch(`${CONFIG.speciesLookupUrl}/${encodeURIComponent(qid)}`, {
                headers: { Accept: 'application/json' },
            });
            const data = await response.json();
            if (! response.ok) throw new Error(data.message || 'No public information was found for this species.');

            currentSpecies.data = data;
            const entry = openVocabEntries[qid] || {};

            speciesName.textContent = data.common_name || data.scientific_name || qid;
            speciesScientific.textContent = data.scientific_name || '';
            speciesDescription.textContent = data.description || data.description_note || data.short_description || 'No description is available yet.';
            speciesStatus.textContent = data.conservation_status || 'Check IUCN Red List';
            speciesSource.innerHTML = data.source_url
                ? `<a href="${escapeHtml(data.source_url)}" target="_blank" rel="noopener noreferrer" class="underline">${escapeHtml(data.source_url)}</a> · ${escapeHtml(data.source_note || '')}`
                : escapeHtml(data.source_note || '');

            const imageCandidate = data.image_candidate || null;

            if (imageCandidate?.preview_url) {
                speciesImage.src = imageCandidate.preview_url;
                speciesImage.alt = imageCandidate.alt || `Photo of a ${data.common_name || 'species'}`;
                speciesImage.classList.remove('hidden');
            } else {
                speciesImage.removeAttribute('src');
                speciesImage.classList.add('hidden');
            }

            speciesQidInput.value = qid;
            speciesConfidenceInput.value = String(confidence || 0);
            speciesTierInput.value = String(tier || 1);
            speciesImageTokenInput.value = imageCandidate?.token || '';
            speciesSourceInput.value = data.source_url || '';
            speciesCommonNameInput.value = data.common_name || entry.common_name || '';
            speciesScientificInput.value = data.scientific_name || entry.scientific_name || '';
            speciesGroupNameInput.value = data.group_name || entry.group_name || '';
            speciesDescriptionInput.value = data.description || '';
            speciesHabitatInput.value = data.habitat || '';
            speciesClimateInput.value = data.climate_impact || '';
            speciesStatusInput.value = data.conservation_status || '';

            const rare = ['Critically Endangered', 'Endangered', 'Vulnerable'].includes(data.conservation_status);
            speciesRare.classList.toggle('hidden', ! rare);

            if (! data.description) {
                speciesDescriptionInput.placeholder = data.description_note || 'Please write a short description.';
                speciesDescriptionInput.required = true;
            }

            const match = window.EcoSightOpenVocab.matchAddAsType({
                common_name: data.common_name || entry.common_name,
                scientific_name: data.scientific_name || entry.scientific_name,
            }, libraryAnimals);

            if (match) {
                speciesAddAsTypeTarget.textContent = match.common_name;
                speciesAddAsType.classList.remove('hidden');
                speciesAddAsType.dataset.animalId = match.id;
            } else {
                speciesAddAsType.classList.add('hidden');
                speciesAddAsTypeTarget.textContent = '';
                speciesAddAsType.dataset.animalId = '';
            }

            speciesReview.classList.remove('hidden');
            speciesMessage.textContent = 'This species is not in the EcoSight library yet.';
        } catch (error) {
            speciesReview.classList.add('hidden');
            speciesMessage.textContent = error.message;
            sendEvent('lookup-failed', { reason: qid });
        }
    }

    /**
     * Add the species the user picked, either as a new library animal or as a
     * type of an animal that is already in the library.
     */
    async function addSpecies(asTypeOf) {
        if (! currentSpecies) return;

        const body = {
            qid: currentSpecies.qid,
            confidence: currentSpecies.confidence,
            tier: currentSpecies.tier || 1,
            common_name: speciesCommonNameInput.value.trim(),
            scientific_name: speciesScientificInput.value.trim(),
            group_name: speciesGroupNameInput.value.trim(),
            description: speciesDescriptionInput.value.trim(),
            habitat: speciesHabitatInput.value.trim(),
            climate_impact: speciesClimateInput.value.trim(),
            conservation_status: speciesStatusInput.value,
            image_candidate_token: speciesImageTokenInput.value,
            source_url: speciesSourceInput.value,
        };

        if (! body.description) {
            speciesMessage.textContent = 'Please write a short description before adding this species.';
            speciesDescriptionInput.focus();
            return;
        }

        if (asTypeOf) {
            body.as_type_of = Number(asTypeOf);
            body.kind = 'species';
        }

        speciesMessage.textContent = 'Adding...';
        speciesAdd.disabled = true;
        speciesAddAsType.disabled = true;

        try {
            const response = await fetch(CONFIG.fromScanUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': CONFIG.csrf,
                },
                body: JSON.stringify(body),
            });
            const data = await response.json();
            if (! response.ok) {
                const error = new Error(data.message || 'Unable to add this species.');
                error.animalUrl = data.animal_url || null;
                throw error;
            }

            speciesMessage.textContent = data.message;
            speciesReview.classList.add('hidden');

            // The library changed, so the supported labels and the CLIP text
            // embeddings have to be refreshed.
            await loadSupportedLabels();
            await refreshClipEmbeddings();
            sendEvent('animal-added-from-scan', { reason: currentSpecies.qid });
        } catch (error) {
            if (error.animalUrl) {
                speciesMessage.innerHTML = `${escapeHtml(error.message)} <a href="${escapeHtml(error.animalUrl)}" class="underline">View the existing animal</a>`;
            } else {
                speciesMessage.textContent = error.message;
            }
            speciesAdd.disabled = false;
            speciesAddAsType.disabled = false;
        }
    }

    function startResult(decision, clipResult) {
        currentVariety = buildVariety(clipResult, decision.label);
        currentResult = {
            label: decision.label,
            modelSource: decision.modelSource || 'clip',
            confidence: Number(decision.confidence) || 0,
            responseTime: decision.responseTime || 0,
            wholeImage: decision.wholeImage !== false,
            rule: decision.rule,
            status: decision.status,
            needsConfirmation: decision.status !== 'detected',
            top2: topTwo(decision),
            userConfirmed: false,
            variety: null,
        };
        lastResultAt = Date.now();

        if (currentResult.needsConfirmation) {
            setStatus('Low confidence — please confirm', 'warning');
            sendEvent('low-confidence', { responseTime: currentResult.responseTime });
        }

        if (lastLoadedLabel !== decision.label) {
            lastLoadedLabel = decision.label;
            setStatus(`Looking up ${friendlyLabel(decision.label)}…`, 'neutral');
            loadAnimal(currentResult);
        } else if (currentAnimal) {
            renderAnimal(currentAnimal, currentActions);
        }
    }

    function selectCandidate(label) {
        const candidates = (lastDecision && lastDecision.candidates) || [];
        const candidate = candidates.find((entry) => entry.label === label);
        if (!candidate) return;

        const clipResult = lastClipVector ? { vector: lastClipVector } : null;
        currentVariety = buildVariety(clipResult, label);
        currentResult = {
            label,
            modelSource: 'clip',
            confidence: candidate.probability,
            responseTime: lastDecision.responseTime || 0,
            wholeImage: true,
            rule: 'clip-unsure-choice',
            status: 'unsure',
            needsConfirmation: true,
            top2: candidate,
            userConfirmed: false,
            variety: null,
        };
        lastResultAt = Date.now();
        lastLoadedLabel = label;
        setStatus(`Looking up ${friendlyLabel(label)}…`, 'neutral');
        loadAnimal(currentResult);
    }

    async function runRecognition(source, options = {}) {
        const isUpload = Boolean(options.upload);
        if (recognitionRunning) return;
        recognitionRunning = true;
        const startedAt = performance.now();

        try {
            let clipResult = null;
            let clipDecision = null;

            if (clipState === 'ready') {
                try {
                    clipResult = await runClip(source);
                    lastClipVector = clipResult.vector;
                    lastClipDistractors = clipResult.distractors;
                    lastClipOutOfScope = clipResult.outOfScope;
                    clipDecision = Logic.decideFromClip(clipResult.animals, clipResult.distractors);
                } catch (error) {
                    clipState = 'unavailable';
                    showClipNotice('clip-runtime-failed');
                    clipResult = null;
                    clipDecision = null;
                }
            }

            let fallbackDecision = null;
            const needsFallback = !clipDecision
                || clipDecision.status === 'none'
                || clipDecision.status === 'low-confidence'
                || clipDecision.status === 'unsure';
            if (needsFallback) {
                fallbackDecision = await runFallbackDecision(source);
            }

            let decision = clipDecision && fallbackDecision
                ? Logic.applyAgreement(clipDecision, fallbackDecision)
                : (clipDecision || fallbackDecision);
            if (!decision) {
                decision = { status: 'none', rule: 'none', box: null, wholeImage: true };
            }
            decision.responseTime = Math.round(performance.now() - startedAt);
            lastDecision = decision;

            if (!isUpload) {
                clipStability = Logic.stabilize(
                    clipStability,
                    decision.status === 'detected' ? decision.label : null,
                    performance.now()
                );
                if (decision.status === 'detected' && !clipStability.stable) {
                    setStatus('Hold steady…', 'neutral');
                    return;
                }
            }

            if (CONFIG.debug) {
                debug({
                    thresholds: minConfidences,
                    config: CFG,
                    clipTop: clipResult ? clipResult.animals.slice(0, 5).map((entry) => ({
                        label: entry.label,
                        probability: Number(entry.probability.toFixed(4)),
                    })) : null,
                    distractorTop: clipResult ? clipResult.distractors.slice(0, 3).map((entry) => ({
                        label: entry.label,
                        probability: Number(entry.probability.toFixed(4)),
                    })) : null,
                    fallbackTop: fallbackDecision ? fallbackDecision.candidates : null,
                    decision,
                });
            }

            if (decision.status === 'detected' || decision.status === 'low-confidence') {
                clearOverlay();
                startResult(decision, clipResult);
                emit('animal-detected', { prediction: decision, responseTime: decision.responseTime });
                if (decision.status === 'low-confidence') {
                    maybeRunOpenVocab(clipResult);
                }
                return;
            }

            if (decision.status === 'unsure') {
                clearOverlay();
                currentResult = null;
                lastResultAt = Date.now();
                setStatus('Not sure yet', 'warning');
                setEmptyResult(
                    'Which animal is it?',
                    'CLIP sees a close match between several animals. Tap the one you see.',
                    { keepResult: true }
                );
                setAlternatives(
                    (decision.candidates || []).map((entry) => ({
                        label: friendlyLabel(entry.label),
                        raw: entry.label,
                        probability: entry.probability,
                    })),
                    { clickable: true, title: 'Which one is it?' }
                );
                emit('detection-failed', { reason: 'unsure', responseTime: decision.responseTime });
                sendEvent('low-confidence', { responseTime: decision.responseTime });
                maybeRunOpenVocab(clipResult);
                return;
            }

            if (decision.status === 'distractor') {
                clearOverlay();
                setStatus('No animal detected', 'neutral');
                showDistractor(decision);
                lastResultAt = Date.now();
                emit('detection-failed', { reason: 'distractor', responseTime: decision.responseTime });
                sendEvent('detection-failed', { reason: `distractor: ${decision.label}`, responseTime: decision.responseTime });
                maybeRunOpenVocab(clipResult);
                return;
            }

            if (decision.status === 'not-in-library') {
                clearOverlay();
                setStatus('Not in the library yet', 'warning');
                showNotInLibrary(decision);
                emit('detection-failed', { reason: 'not-in-library', responseTime: decision.responseTime });
                sendEvent('detection-failed', { reason: `not-in-library: ${decision.className}`, responseTime: decision.responseTime });
                maybeRunOpenVocab(clipResult);
                return;
            }

            // Nothing usable this frame.
            clearOverlay();
            if (isUpload) {
                setStatus('No library match - check the species list', 'neutral');
                setEmptyResult(
                    'Not in the library',
                    'EcoSight did not recognise this as one of its supported animals. Check the possible species below.'
                );
            } else if (Date.now() - lastResultAt > CFG.RESULT_CLEAR_MS) {
                setStatus('No supported animal detected', 'neutral');
                setEmptyResult();
            }
            emit('detection-failed', { reason: 'no-animal-detected', responseTime: decision.responseTime });
            sendEvent('detection-failed', { reason: 'no-animal-detected', responseTime: decision.responseTime });
            maybeRunOpenVocab(clipResult);
        } catch (error) {
            setStatus('Detection failed', 'error');
            setEmptyResult('Detection failed', 'The model could not process this frame. Try again or upload a different photo.');
            emit('detection-failed', { reason: error.message });
            sendEvent('detection-failed', { reason: error.message, responseTime: Math.round(performance.now() - startedAt) });
        } finally {
            recognitionRunning = false;
        }
    }

    function detectionLoop(timestamp) {
        if (!detectionRunning) return;
        animationFrame = requestAnimationFrame(detectionLoop);

        if (timestamp - lastDetectionTime < FRAME_INTERVAL_MS) return;
        lastDetectionTime = timestamp;
        if (video.readyState < 2) return;

        if (Date.now() - lastClipAt < CFG.CAMERA_CLIP_INTERVAL_MS) return;
        lastClipAt = Date.now();
        sizeCanvas(video);
        runRecognition(video, { upload: false });
    }

    async function setupCameraSwitch() {
        try {
            const devices = await navigator.mediaDevices.enumerateDevices();
            const cameras = devices.filter((device) => device.kind === 'videoinput');
            switchButton.classList.toggle('hidden', cameras.length < 2);
        } catch (error) {
            switchButton.classList.add('hidden');
        }
    }

    function setPaused(value) {
        paused = value;
        pauseButton.textContent = paused ? 'Resume' : 'Pause';
        pauseButton.classList.toggle('hidden', !value && !stream);
    }

    async function startCamera() {
        stopCamera();
        image.classList.add('hidden');
        video.classList.remove('hidden');
        clearOverlay();
        showLoading('Requesting camera permission…');

        if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
            hideLoading();
            setStatus('Camera unavailable', 'error');
            setEmptyResult('Camera needs HTTPS or localhost', 'Open EcoSight through localhost or HTTPS, then use the upload option if camera access is not available.');
            emit('detection-failed', { reason: 'insecure-context' });
            sendEvent('detection-failed', { reason: 'insecure-context' });
            return;
        }

        const cameraStartClick = performance.now();
        try {
            stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: { ideal: cameraFacing }, width: { ideal: 1280 }, height: { ideal: 720 } },
                audio: false,
            });
            video.srcObject = stream;
            await video.play();
            const cameraStartMs = Math.round(performance.now() - cameraStartClick);
            hideLoading();
            setStatus('Camera ready', 'success');
            emit('camera-ready');
            sendEvent('camera-started', { cameraStartMs });
            setPaused(false);
            setupCameraSwitch();
            clipStability = null;
            lastClipAt = 0;
            detectionRunning = true;
            emit('detection-start');
            animationFrame = requestAnimationFrame(detectionLoop);
        } catch (error) {
            hideLoading();
            setStatus('Camera permission denied', 'error');
            setEmptyResult('Camera permission denied', 'Allow camera access in your browser settings or use the upload-photo fallback.');
            emit('detection-failed', { reason: error.name || 'camera-error' });
            sendEvent('detection-failed', { reason: error.name || 'camera-error' });
        }
    }

    function stopCamera() {
        detectionRunning = false;
        if (animationFrame) cancelAnimationFrame(animationFrame);
        animationFrame = null;
        if (stream) {
            stream.getTracks().forEach((track) => track.stop());
            stream = null;
        }
        video.srcObject = null;
        setPaused(false);
        switchButton.classList.add('hidden');
        pauseButton.classList.add('hidden');
    }

    async function handleUpload(file) {
        if (!file) return;
        stopCamera();
        video.classList.add('hidden');
        image.classList.remove('hidden');
        clearOverlay();
        mobileTopCache = null;
        clipStability = null;
        showLoading('Preparing the uploaded photo…');
        setStatus('Processing photo…', 'neutral');

        const objectUrl = URL.createObjectURL(file);
        image.onload = async () => {
            hideLoading();
            if (!sizeCanvas(image)) return;
            await runRecognition(image, { upload: true });
            URL.revokeObjectURL(objectUrl);
        };
        image.onerror = () => {
            hideLoading();
            setStatus('Invalid photo', 'error');
            setEmptyResult('Could not read the photo', 'Choose a JPG, PNG, or another browser-supported image.');
            URL.revokeObjectURL(objectUrl);
        };
        image.src = objectUrl;
    }

    async function saveScan() {
        if (!currentResult || saveButton.disabled) return;
        const wasUnconfirmed = Boolean(currentResult.needsConfirmation) && !currentResult.userConfirmed;
        if (wasUnconfirmed) {
            currentResult.userConfirmed = true;
        }
        saveButton.disabled = true;
        saveMessage.textContent = 'Saving…';

        const body = {
            detected_label: currentResult.label,
            model_source: currentResult.modelSource,
            confidence: currentResult.confidence,
            user_confirmed: Boolean(currentResult.userConfirmed),
            response_time_ms: currentResult.responseTime,
        };
        if (currentResult.top2 && currentResult.top2.label) {
            body.top2_label = currentResult.top2.label;
            body.top2_confidence = currentResult.top2.confidence;
        }
        if (currentResult.variety && currentResult.variety.label) {
            body.variety_label = currentResult.variety.label;
            body.variety_confidence = currentResult.variety.confidence;
            body.variety_confirmed = Boolean(currentResult.variety.confirmed);
        }

        try {
            const response = await fetch(CONFIG.scanUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': CONFIG.csrf,
                },
                body: JSON.stringify(body),
            });

            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Unable to save the scan.');
            currentScanId = data.scan_id;
            feedbackSubmitted = false;
            saveMessage.textContent = 'Saved to My Scans. Did EcoSight get it right?';
            feedbackBox.classList.remove('hidden');
        } catch (error) {
            if (wasUnconfirmed) currentResult.userConfirmed = false;
            saveMessage.textContent = error.message;
            saveButton.disabled = false;
        }
    }

    async function submitFeedback(isCorrect) {
        if (!currentScanId || feedbackSubmitted) return;
        const actualLabel = isCorrect ? null : feedbackLabelInput.value.trim();
        if (!isCorrect && !actualLabel) {
            feedbackLabelWrap.classList.remove('hidden');
            feedbackMessage.textContent = 'Choose the animal from the list below.';
            return;
        }
        feedbackSubmitted = true;
        feedbackMessage.textContent = 'Saving feedback…';

        try {
            const response = await fetch(CONFIG.feedbackUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': CONFIG.csrf,
                },
                body: JSON.stringify({
                    scan_id: currentScanId,
                    is_correct: isCorrect,
                    actual_label: actualLabel,
                }),
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Could not save feedback.');
            feedbackMessage.textContent = 'Feedback recorded. Thank you!';
            feedbackYesButton.disabled = true;
            feedbackNoButton.disabled = true;
        } catch (error) {
            feedbackSubmitted = false;
            feedbackMessage.textContent = error.message;
        }
    }

    async function refreshExtendedAvailability() {
        if (! openVocabExtended) return;
        try {
            const store = await import('/js/open-vocab-store.js');
            const cached = await store.getCachedVocabulary('tier2');
            if (cached) {
                openVocabExtended.textContent = 'Use the downloaded extended species list';
            }
        } catch {
            // IndexedDB may be unavailable in private browsing; the download
            // path still works.
        }
    }

    async function boot() {
        const startedAt = performance.now();
        try {
            setStatus('Loading supported animals…', 'neutral');
            showLoading('Loading supported animal list…');
            refreshExtendedAvailability();
            await loadSupportedLabels();

            showLoading('Loading CLIP model…');
            setStatus('Loading AI model…', 'neutral');
            await loadClipStack();
            const animalCount = Object.keys(labelNames).length;

            // Warm up the species vocabulary in the background: it is only
            // needed when the library does not recognise the animal.
            openVocabRequest('load-tier1').catch(() => {
                openVocabWorker = null;
            });

            if (clipState === 'ready') {
                setStatus(`AI ready · ${animalCount} animals`, 'success');
                sendEvent('model-loaded', { modelLoadMs: Math.round(performance.now() - startedAt) });
                ensureMobileModel().then(() => {}).catch(() => showMobileNetNotice());
            } else {
                setStatus('Loading fallback model…', 'neutral');
                showLoading('Loading fallback model…');
                await ensureMobileModel().catch(() => showMobileNetNotice());
                if (mobileModel) {
                    setStatus(`Fallback ready · ${animalCount} animals`, 'success');
                    sendEvent('model-loaded', { modelLoadMs: Math.round(performance.now() - startedAt) });
                } else {
                    setStatus('AI model unavailable', 'error');
                    setEmptyResult('AI model unavailable', 'Neither recognition model could load. Check your connection and reload the page.');
                }
            }
            hideLoading();
        } catch (error) {
            hideLoading();
            setStatus('Model failed to load', 'error');
            setEmptyResult('AI model unavailable', 'Check your internet connection and reload the page.');
            emit('detection-failed', { reason: 'model-load-failed' });
            sendEvent('detection-failed', { reason: 'model-load-failed' });
        }
    }

    window.addEventListener('resize', () => alignOverlay());

    document.addEventListener('camera-ready', () => setStatus('Camera ready', 'success'));
    startButton.addEventListener('click', startCamera);
    switchButton.addEventListener('click', () => {
        cameraFacing = cameraFacing === 'environment' ? 'user' : 'environment';
        startCamera();
    });
    pauseButton.addEventListener('click', () => {
        if (paused) {
            video.play().then(() => {
                setPaused(false);
                detectionRunning = true;
                animationFrame = requestAnimationFrame(detectionLoop);
                setStatus('Camera ready', 'success');
            }).catch(() => {
                setStatus('Could not resume camera', 'error');
            });
        } else {
            detectionRunning = false;
            if (animationFrame) cancelAnimationFrame(animationFrame);
            video.pause();
            setPaused(true);
            setStatus('Detection paused', 'warning');
        }
    });
    stopButton.addEventListener('click', () => {
        stopCamera();
        clearOverlay();
        setStatus('Camera stopped', 'neutral');
    });
    photoInput.addEventListener('change', (event) => handleUpload(event.target.files[0]));
    retryRecordButton.addEventListener('click', () => {
        if (retryCandidate) {
            hideRecordMissing();
            setStatus(`Looking up ${friendlyLabel(retryCandidate.label)}…`, 'neutral');
            loadAnimal(retryCandidate);
        }
    });
    resultAlternatives.addEventListener('click', (event) => {
        const candidate = event.target.closest('[data-candidate-label]');
        if (candidate) {
            selectCandidate(candidate.dataset.candidateLabel);
            return;
        }
        if (event.target.id === 'dismiss-alternatives') {
            setEmptyResult();
        }
    });
    openVocabCandidates?.addEventListener('click', (event) => {
        const candidate = event.target.closest('[data-species-qid]');
        if (! candidate) return;
        const qid = candidate.dataset.speciesQid;
        if (! qid) return;
        const entry = openVocabEntries[qid] || {};
        showSpecies(qid, entry.probability || 0, entry.tier || 1);
        sendEvent(entry.tier === 2 ? 'extended-candidate-picked' : 'open-vocab-candidate-picked', { reason: qid });
    });
    openVocabExtended?.addEventListener('click', async () => {
        openVocabExtended.disabled = true;
        if (openVocabProgress) openVocabProgress.classList.remove('hidden');
        if (openVocabProgressBar) openVocabProgressBar.style.width = '0%';
        try {
            await ensureOpenVocabTier2();
            if (openVocabProgressText) {
                openVocabProgressText.textContent = 'Extended list ready. Matching again…';
            }
            if (lastClipVector) {
                const result = await runOpenVocab(lastClipVector, lastClipDistractors, lastClipOutOfScope);
                renderOpenVocab(result);
            }
        } catch (error) {
            if (openVocabProgressText) openVocabProgressText.textContent = error.message;
        } finally {
            openVocabExtended.disabled = false;
        }
    });
    openVocabNone?.addEventListener('click', () => {
        openVocabCandidates.innerHTML = '';
        openVocabNone.classList.add('hidden');
        openVocabMessage.textContent = 'No match found.';
        sendEvent(lastOpenVocabTier2 ? 'extended-none-of-these' : 'open-vocab-run', { reason: 'none-of-these' });
    });
    openVocabExtendedDecline?.addEventListener('click', () => {
        openVocabExtended.classList.add('hidden');
        openVocabExtendedDecline.classList.add('hidden');
        if (openVocabProgress) openVocabProgress.classList.add('hidden');
        sendEvent('extended-declined', { reason: 'tier2' });
    });
    speciesReview?.addEventListener('submit', (event) => {
        event.preventDefault();
        addSpecies(null);
    });
    speciesAdd?.addEventListener('click', () => addSpecies(null));
    speciesAddAsType?.addEventListener('click', () => {
        const animalId = speciesAddAsType.dataset.animalId;
        if (animalId) addSpecies(animalId);
    });
    varietySelect?.addEventListener('change', () => {
        if (!currentResult) return;
        const label = varietySelect.value;
        if (!label) {
            currentResult.variety = null;
            varietyMessage.textContent = 'Optional: choose the breed or variety if you know it.';
            return;
        }
        const confidence = (currentVariety && currentVariety.scoreMap[label]) || 0;
        currentResult.variety = { label, confidence, confirmed: true };
        varietyMessage.textContent = `Using ${label}.`;
    });
    saveButton.addEventListener('click', saveScan);
    feedbackYesButton.addEventListener('click', () => submitFeedback(true));
    feedbackNoButton.addEventListener('click', () => {
        feedbackLabelWrap.classList.remove('hidden');
        feedbackLabelInput.focus();
    });
    feedbackLabelInput.addEventListener('change', () => {
        if (feedbackLabelInput.value.trim()) submitFeedback(false);
    });

    boot();
})();
