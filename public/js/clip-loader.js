/**
 * EcoSight CLIP loader (browser ES module).
 *
 * Loads the pinned Transformers.js bundle (vendored by `npm run download:clip`
 * into /vendor/transformers/), the q8 CLIP vision encoder, and the precomputed
 * text embeddings. Model weights are served same-origin from /models/ when
 * present (local mode); otherwise the Hugging Face Hub is used as a fallback.
 *
 * The text encoder is only loaded when the committed text-embedding file is
 * stale relative to the prompts served by /scanner/labels (e.g. an admin
 * edited an animal's clip_names), so normal scans download ~85 MiB, not the
 * full ~215 MiB.
 *
 * Usage:
 *   const clip = await loadClip({ onProgress });
 *   const { embeddings } = await ensureTextEmbeddings(clip, labels, { onProgress });
 *   const { animals, distractors } = await classifyImage(clip, canvas, { embeddings });
 *   const varieties = scoreVarieties(vector, embeddings, 'dog');
 *   const decision = EcoSightLogic.decideFromClip(animals, distractors);
 */
const MODEL_ID = 'Xenova/clip-vit-base-patch32';
const BUNDLE_URL = '/vendor/transformers/transformers.min.js';
const LOCAL_MODEL_BASE = '/models/';
const EMBEDDINGS_URL = '/data/clip-text-embeddings.json';
const EMBEDDING_DIM = 512;

let transformersPromise = null;

function getTransformers() {
    if (!transformersPromise) {
        transformersPromise = import(BUNDLE_URL);
    }
    return transformersPromise;
}

function hasLocalModels() {
    return fetch(`${LOCAL_MODEL_BASE}${MODEL_ID}/config.json`, { method: 'HEAD', cache: 'no-store' })
        .then((response) => response.ok)
        .catch(() => false);
}

/**
 * Load the vision encoder (q8) and its processor.
 *
 * @param {{ onProgress?: (payload: {file: string, status: string, loaded?: number, total?: number}) => void }} [options]
 * @returns {Promise<{ transformers: object, processor: object, vision: object, mode: 'local'|'remote' }>}
 */
export async function loadClip(options = {}) {
    const transformers = await getTransformers();
    const env = transformers.env;
    const local = await hasLocalModels();

    env.allowLocalModels = local;
    env.allowRemoteModels = !local;
    env.localModelPath = LOCAL_MODEL_BASE;

    const config = {};
    if (!local) {
        env.remoteHost = 'https://huggingface.co/';
    }

    const processor = await transformers.AutoProcessor.from_pretrained(MODEL_ID, {
        ...config,
        progress_callback: options.onProgress,
    });
    const vision = await transformers.CLIPVisionModelWithProjection.from_pretrained(MODEL_ID, {
        ...config,
        dtype: 'q8',
        progress_callback: options.onProgress,
    });

    return { transformers, processor, vision, mode: local ? 'local' : 'remote' };
}

/** L2-normalise a numeric vector (returns a new array). */
export function normalizeVector(values) {
    let sum = 0;
    for (let i = 0; i < values.length; i++) sum += values[i] * values[i];
    const norm = Math.sqrt(sum) || 1;
    const out = new Array(values.length);
    for (let i = 0; i < values.length; i++) out[i] = values[i] / norm;
    return out;
}

function dot(a, b) {
    let sum = 0;
    for (let i = 0; i < a.length; i++) sum += a[i] * b[i];
    return sum;
}

function softmaxFromSimilarities(similarities, scale) {
    let max = -Infinity;
    for (const value of similarities) if (value > max) max = value;
    let total = 0;
    const exps = similarities.map((value) => {
        const weight = Math.exp(scale * (value - max));
        total += weight;
        return weight;
    });
    return exps.map((weight) => weight / (total || 1));
}

/**
 * Fetch and validate the committed text-embedding file.
 *
 * @param {string} [url]
 * @returns {Promise<{version:number, model:string, dtype:string, dim:number, animals:object, varieties:object, distractors:object[]}>}
 */
export async function loadTextEmbeddings(url = EMBEDDINGS_URL) {
    const response = await fetch(url, { cache: 'no-cache' });
    if (!response.ok) {
        throw new Error(`Text embeddings unavailable (${response.status} ${response.statusText})`);
    }
    const data = await response.json();
    if (data.dim !== EMBEDDING_DIM || !data.animals || !data.distractors) {
        throw new Error('Text embeddings file has an unexpected shape');
    }
    return data;
}

function samePrompts(a, b) {
    if (!Array.isArray(a) || !Array.isArray(b) || a.length !== b.length) return false;
    for (let i = 0; i < a.length; i++) if (a[i] !== b[i]) return false;
    return true;
}

/**
 * Check whether the committed embeddings still correspond to the prompts the
 * server reports. `labels.clipPrompts` must be shaped as:
 *   { animals: { [coco_label]: string[] }, varieties: { [animal|label]: string[] } }
 * Varieties are keyed `${animal}:${label}` in the embeddings file; the labels
 * payload may key them either way (both are checked).
 *
 * @param {object} embeddings
 * @param {{ clipPrompts?: { animals?: object, varieties?: object } }} labels
 * @returns {boolean}
 */
export function embeddingsMatchLabels(embeddings, labels) {
    const prompts = labels && labels.clipPrompts;
    if (!prompts || !prompts.animals) return false;

    for (const [animal, list] of Object.entries(prompts.animals)) {
        const entry = embeddings.animals[animal];
        if (!entry || !samePrompts(entry.prompts, list)) return false;
    }

    for (const [key, list] of Object.entries(prompts.varieties || {})) {
        const entry = embeddings.varieties[key] || embeddings.varieties[key.replace('|', ':')];
        if (!entry || !samePrompts(entry.prompts, list)) return false;
    }

    return true;
}

async function loadTextEncoder(transformers, onProgress) {
    const tokenizer = await transformers.AutoTokenizer.from_pretrained(MODEL_ID, { progress_callback: onProgress });
    const model = await transformers.CLIPTextModelWithProjection.from_pretrained(MODEL_ID, {
        dtype: 'q8',
        progress_callback: onProgress,
    });
    return { tokenizer, model };
}

async function embedPrompts(encoder, prompts) {
    const inputs = encoder.tokenizer(prompts, { padding: true, truncation: true });
    const output = await encoder.model(inputs);
    const dims = output.text_embeds.dims;
    const data = output.text_embeds.data;
    if (dims[0] !== prompts.length || dims[1] !== EMBEDDING_DIM) {
        throw new Error(`Unexpected text_embeds dims ${JSON.stringify(dims)}`);
    }
    const averaged = new Array(EMBEDDING_DIM).fill(0);
    for (let row = 0; row < prompts.length; row++) {
        for (let column = 0; column < EMBEDDING_DIM; column++) {
            averaged[column] += data[row * EMBEDDING_DIM + column];
        }
    }
    for (let column = 0; column < EMBEDDING_DIM; column++) averaged[column] /= prompts.length;
    return normalizeVector(averaged);
}

/**
 * Return fresh text embeddings: the committed file when it matches the
 * labels payload, otherwise recomputed animals/varieties (distractors are
 * static and reused from the file).
 *
 * @param {{ transformers: object }} clip result of loadClip()
 * @param {{ clipPrompts?: object }} labels /scanner/labels payload
 * @param {{ onProgress?: Function, url?: string }} [options]
 * @returns {Promise<{ embeddings: object, recomputed: boolean }>}
 */
export async function ensureTextEmbeddings(clip, labels, options = {}) {
    const embeddings = await loadTextEmbeddings(options.url);
    if (embeddingsMatchLabels(embeddings, labels)) {
        return { embeddings, recomputed: false };
    }

    const encoder = await loadTextEncoder(clip.transformers, options.onProgress);
    const animalPrompts = (labels.clipPrompts && labels.clipPrompts.animals) || {};
    const varietyPrompts = (labels.clipPrompts && labels.clipPrompts.varieties) || {};

    const animals = {};
    for (const [animal, prompts] of Object.entries(animalPrompts)) {
        animals[animal] = { prompts, embedding: await embedPrompts(encoder, prompts) };
    }

    const varieties = {};
    for (const [key, prompts] of Object.entries(varietyPrompts)) {
        const normalizedKey = key.replace('|', ':');
        varieties[normalizedKey] = { prompts, embedding: await embedPrompts(encoder, prompts) };
    }

    return { embeddings: { ...embeddings, animals, varieties }, recomputed: true };
}

/**
 * Convert an image-like browser input to a RawImage the processor accepts.
 * RawImage inputs pass through; canvas/image/video are drawn to a canvas
 * first (the processor's preprocessing expects a RawImage-like object).
 */
function toRawImage(transformers, input) {
    const RawImage = transformers.RawImage;
    if (RawImage && input instanceof RawImage) return input;

    const width = input.videoWidth || input.naturalWidth || input.width;
    const height = input.videoHeight || input.naturalHeight || input.height;
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    canvas.getContext('2d').drawImage(input, 0, 0, width, height);

    return RawImage && typeof RawImage.fromCanvas === 'function'
        ? RawImage.fromCanvas(canvas)
        : canvas;
}

/**
 * Embed an image-like input (canvas, image element, video frame, RawImage).
 *
 * @param {{ transformers: object, processor: object, vision: object }} clip
 * @param {*} image
 * @returns {Promise<Float32Array|number[]>} normalised 512-d embedding
 */
export async function imageVector(clip, image) {
    const inputs = await clip.processor(toRawImage(clip.transformers, image));
    const output = await clip.vision({ pixel_values: inputs.pixel_values });
    return normalizeVector(Array.from(output.image_embeds.data));
}

function scoreGroups(vector, groups, scale) {
    const similarities = groups.map((group) => dot(vector, group.embedding));
    const probabilities = softmaxFromSimilarities(similarities, scale);
    return groups
        .map((group, index) => ({
            label: group.label,
            probability: probabilities[index],
            similarity: similarities[index],
        }))
        .sort((a, b) => b.probability - a.probability);
}

/**
 * Score one frame against every animal and distractor class.
 *
 * @param {{ processor: object, vision: object }} clip
 * @param {*} image canvas/image element/video frame
 * @param {{ embeddings: object, scale?: number }} options
 * @returns {Promise<{ vector: number[], animals: Array, distractors: Array, outOfScope: Array }>}
 */
export async function classifyImage(clip, image, options) {
    const embeddings = options.embeddings;
    const scale = Number.isFinite(options.scale) ? options.scale : 100;
    const vector = await imageVector(clip, image);

    const animals = scoreGroups(
        vector,
        Object.entries(embeddings.animals).map(([label, entry]) => ({ label, embedding: entry.embedding })),
        scale
    );
    const distractors = scoreGroups(
        vector,
        embeddings.distractors.map((entry) => ({ label: entry.label, embedding: entry.embedding })),
        scale
    );
    const outOfScope = scoreGroups(
        vector,
        (embeddings.out_of_scope || []).map((entry) => ({ label: entry.label, embedding: entry.embedding })),
        scale
    );

    return { vector, animals, distractors, outOfScope };
}

/**
 * Score the varieties of one animal against an existing image vector.
 *
 * @param {number[]} vector from classifyImage()
 * @param {object} embeddings
 * @param {string} animalLabel coco_label of the detected animal
 * @param {number} [scale]
 * @returns {Array<{label:string, probability:number, similarity:number}>}
 */
export function scoreVarieties(vector, embeddings, animalLabel, scale = 100) {
    const prefix = `${animalLabel}:`;
    const groups = Object.entries(embeddings.varieties)
        .filter(([key]) => key.startsWith(prefix))
        .map(([key, entry]) => ({ label: key.slice(prefix.length), embedding: entry.embedding }));

    if (groups.length === 0) return [];
    return scoreGroups(vector, groups, scale);
}
