/**
 * Node API smoke test for the pinned Transformers.js CLIP integration.
 *
 * Proves, before any browser work: the pinned package installs, the CLIP
 * classes we import exist, dtype q8 loads Xenova/clip-vit-base-patch32,
 * processor/tokenizer output keys are what the client code expects, the
 * progress_callback payload shape is usable, and inference returns
 * [1, 512] embeddings with finite values.
 *
 * Model files come from public/models (curl download; Node cannot complete
 * TLS to huggingface.co on this machine — see scripts/download-clip.mjs).
 *
 * Run with: npm run smoke:clip   (installs scripts/.clip-tool + model on first run)
 * Exits non-zero on any failed assertion.
 */
import { existsSync, statSync } from 'node:fs';
import { join } from 'node:path';
import {
    ensureClipModel,
    importTransformers,
    TRANSFORMERS_VERSION,
    useLocalModels,
} from './ensure-clip-tool.mjs';

const MODEL_ID = 'Xenova/clip-vit-base-patch32';
const DTYPE = 'q8';
const EMBEDDING_DIM = 512;

const failures = [];
const check = (condition, message) => {
    if (!condition) failures.push(message);
    console.log(`${condition ? 'PASS' : 'FAIL'}  ${message}`);
};

const { root: modelsRoot, modelDir } = await ensureClipModel();
const transformers = await importTransformers();
useLocalModels(transformers, modelsRoot);

check(transformers.env != null, `transformers.js v${TRANSFORMERS_VERSION} imports (env export present)`);
check(transformers.env.allowRemoteModels === false && transformers.env.allowLocalModels === true,
    `local-only model mode configured (${transformers.env.localModelPath})`);
for (const name of ['CLIPVisionModelWithProjection', 'CLIPTextModelWithProjection', 'AutoProcessor', 'AutoTokenizer', 'RawImage']) {
    check(typeof transformers[name] === 'function', `export ${name} is a function`);
}
if (failures.length > 0) {
    console.error('\nMissing exports; aborting before any model load.');
    process.exit(1);
}

const {
    CLIPVisionModelWithProjection,
    CLIPTextModelWithProjection,
    AutoProcessor,
    AutoTokenizer,
    RawImage,
} = transformers;

/** @type {Map<string, { total: number, loaded: number, status: string }>} */
const files = new Map();
const progressCallback = (payload) => {
    if (!payload || !payload.file) return;
    const key = `${payload.file}`;
    const entry = files.get(key) ?? { total: 0, loaded: 0, status: '' };
    if (typeof payload.total === 'number') entry.total = Math.max(entry.total, payload.total);
    if (typeof payload.loaded === 'number') entry.loaded = Math.max(entry.loaded, payload.loaded);
    if (payload.status) entry.status = payload.status;
    files.set(key, entry);
};

const startedAt = performance.now();
console.log(`\nLoading ${MODEL_ID} (dtype ${DTYPE}) from ${modelDir}…`);

const loaded = await Promise.all([
    AutoProcessor.from_pretrained(MODEL_ID, { progress_callback: progressCallback }),
    AutoTokenizer.from_pretrained(MODEL_ID, { progress_callback: progressCallback }),
    CLIPVisionModelWithProjection.from_pretrained(MODEL_ID, { dtype: DTYPE, progress_callback: progressCallback }),
    CLIPTextModelWithProjection.from_pretrained(MODEL_ID, { dtype: DTYPE, progress_callback: progressCallback }),
]);
const [processor, tokenizer, visionModel, textModel] = loaded;
const loadMs = Math.round(performance.now() - startedAt);

const localFiles = [
    'config.json', 'preprocessor_config.json', 'tokenizer.json', 'vocab.json', 'merges.txt',
    'onnx/vision_model_quantized.onnx', 'onnx/text_model_quantized.onnx',
].filter((path) => existsSync(join(modelDir, path)));
const localBytes = localFiles.reduce((sum, path) => sum + statSync(join(modelDir, path)).size, 0);
console.log(`Model load: ${loadMs} ms, ${files.size} file event(s), local model ${(localBytes / 1024 / 1024).toFixed(1)} MiB on disk`);

check(loadMs > 0, 'model load completed');
check(files.size > 0, 'progress_callback reported file events');
check([...files.values()].every((file) => file.status === 'done' || file.status === 'ready'),
    'every tracked file reached done/ready status');

const PROMPT = 'a photo of a dog';
const textStart = performance.now();
const textInputs = tokenizer(PROMPT, { padding: true, truncation: true });
check(Array.isArray(textInputs.input_ids?.dims) && textInputs.input_ids.dims.length === 2,
    `tokenizer returns input_ids dims ${JSON.stringify(textInputs.input_ids?.dims)}`);
const textOutput = await textModel(textInputs);
const textMs = Math.round(performance.now() - textStart);
const textEmbeds = textOutput.text_embeds;
check(JSON.stringify(textEmbeds?.dims) === JSON.stringify([1, EMBEDDING_DIM]),
    `text_embeds dims are [1, ${EMBEDDING_DIM}] (got ${JSON.stringify(textEmbeds?.dims)})`);

const width = 96;
const height = 96;
const pixels = new Uint8Array(width * height * 3);
for (let i = 0; i < pixels.length; i++) pixels[i] = (i * 7) % 256;
const image = new RawImage(pixels, width, height, 3);

const imageStart = performance.now();
const imageInputs = await processor(image);
check(Array.isArray(imageInputs.pixel_values?.dims) && imageInputs.pixel_values.dims.length === 4,
    `processor returns pixel_values dims ${JSON.stringify(imageInputs.pixel_values?.dims)}`);
const imageOutput = await visionModel({ pixel_values: imageInputs.pixel_values });
const imageMs = Math.round(performance.now() - imageStart);
const imageEmbeds = imageOutput.image_embeds;
check(JSON.stringify(imageEmbeds?.dims) === JSON.stringify([1, EMBEDDING_DIM]),
    `image_embeds dims are [1, ${EMBEDDING_DIM}] (got ${JSON.stringify(imageEmbeds?.dims)})`);

const textValues = Array.from(textEmbeds.data);
const imageValues = Array.from(imageEmbeds.data);
const norm = (values) => Math.sqrt(values.reduce((sum, value) => sum + value * value, 0));
const textNorm = norm(textValues);
const imageNorm = norm(imageValues);
const cosine = textValues.reduce((sum, value, index) => sum + value * imageValues[index], 0) / (textNorm * imageNorm);

check(Number.isFinite(textNorm) && textNorm > 0, `text embedding norm is finite and > 0 (${textNorm.toFixed(4)})`);
check(Number.isFinite(imageNorm) && imageNorm > 0, `image embedding norm is finite and > 0 (${imageNorm.toFixed(4)})`);
check(Number.isFinite(cosine) && cosine >= -1.0001 && cosine <= 1.0001,
    `cosine(text, image) is a sane number (${cosine.toFixed(4)})`);

console.log(`\nInference: text ${textMs} ms, image ${imageMs} ms, load ${loadMs} ms`);
console.log(`Tracked files:\n${[...files.entries()].map(([file, entry]) => `  ${entry.status.padEnd(6)}  ${file}`).join('\n')}`);

if (failures.length > 0) {
    console.error(`\n${failures.length} assertion(s) failed.`);
    process.exit(1);
}
console.log('\nCLIP Node smoke test passed.');
