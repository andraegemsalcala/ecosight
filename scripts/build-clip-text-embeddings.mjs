/**
 * Precompute CLIP text embeddings so the browser only has to download the
 * vision encoder (~85 MiB): one 512-d vector per animal, distractor class and
 * variety, generated from the current database prompts.
 *
 * The output (public/data/clip-text-embeddings.json) is committed. The browser
 * verifies it against the prompts served by /scanner/labels and falls back to
 * the text encoder only when the data is stale (see public/js/clip-loader.js).
 *
 * Run with: npm run build:text-embeddings [-- --database=path/to.sqlite]
 */
import { existsSync, statSync, writeFileSync, mkdirSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { DatabaseSync } from 'node:sqlite';
import { ensureClipModel, importTransformers, useLocalModels } from './ensure-clip-tool.mjs';

const CLIP_MODEL_ID = 'Xenova/clip-vit-base-patch32';
const DTYPE = 'q8';
const EMBEDDING_DIM = 512;
const OUTPUT_VERSION = 1;

const repoRoot = join(dirname(fileURLToPath(import.meta.url)), '..');
const outputPath = join(repoRoot, 'public', 'data', 'clip-text-embeddings.json');

/**
 * Prompts for confident non-animal scenes. If any of these wins clearly over
 * every animal (DISTRACTOR_ACCEPT / DISTRACTOR_MARGIN in scanner-logic.js) the
 * scanner reports "no animal" instead of guessing.
 */
const DISTRACTOR_PROMPTS = [
    'a photo of a person',
    'a photo of food',
    'a photo of a car',
    'a photo of furniture',
    'a photo of an empty room',
    'a photo of a building',
    'a photo of clothing',
    'a photo of a houseplant',
    'a photo of a toy',
    'a photo of a landscape without animals',
    'a photo of a street scene',
    'a photo of text and signs',
    'a photo of a drawing or cartoon',
    'a photo of a statue',
];

const OUT_OF_SCOPE_PROMPTS = [
    'a photo of a fish',
    'a photo of a shark',
    'a photo of a whale',
    'a photo of a dolphin',
    'a photo of a sea turtle',
    'a photo of a jellyfish',
    'a photo of a coral reef',
    'a photo of an octopus',
    'a photo of a crab',
    'a photo of a snail',
];

const databaseFlag = process.argv.find((arg) => arg.startsWith('--database='));
const databasePath = databaseFlag
    ? resolve(databaseFlag.slice('--database='.length))
    : join(repoRoot, 'database', 'database.sqlite');

if (!existsSync(databasePath)) {
    throw new Error(`Database not found: ${databasePath}`);
}

function readRows() {
    const db = new DatabaseSync(databasePath, { readOnly: true });
    try {
        const columns = db.prepare('PRAGMA table_info(animals)').all().map((column) => column.name);
        if (!columns.includes('clip_names')) {
            throw new Error('animals.clip_names is missing: run php artisan migrate first');
        }
        const hasVarieties = db.prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'animal_varieties'").get();
        if (!hasVarieties) {
            throw new Error('animal_varieties is missing: run php artisan migrate first');
        }

        const animals = db.prepare('SELECT coco_label, common_name, clip_names, is_scannable FROM animals ORDER BY coco_label').all();
        const varieties = db.prepare(`
            SELECT a.coco_label AS animal, v.label AS label, v.clip_names AS clip_names
            FROM animal_varieties v
            JOIN animals a ON a.id = v.animal_id
            ORDER BY a.coco_label, v.label
        `).all();

        return { animals, varieties };
    } finally {
        db.close();
    }
}

function parsePrompts(raw, fallback) {
    if (!raw) return fallback ? [fallback] : [];
    try {
        const parsed = JSON.parse(raw);
        if (Array.isArray(parsed) && parsed.length > 0) {
            return parsed.map(String).map((prompt) => prompt.trim()).filter(Boolean);
        }
    } catch {
        // Fall through to the fallback prompt.
    }
    return fallback ? [fallback] : [];
}

function normalize(values) {
    let sum = 0;
    for (const value of values) sum += value * value;
    const norm = Math.sqrt(sum) || 1;
    return values.map((value) => value / norm);
}

function round(values) {
    return values.map((value) => Math.round(value * 1e6) / 1e6);
}

const rows = readRows();
const animalPrompts = rows.animals
    .filter((row) => Number(row.is_scannable) === 1)
    .map((row) => ({
        key: row.coco_label,
        prompts: parsePrompts(row.clip_names, row.common_name),
    }));
const varietyPrompts = rows.varieties.map((row) => ({
    key: `${row.animal}:${row.label}`,
    prompts: parsePrompts(row.clip_names, row.label),
}));

if (animalPrompts.some((entry) => entry.prompts.length === 0)) {
    throw new Error('Every scannable animal needs at least one clip_names prompt');
}

const { root } = await ensureClipModel();
const transformers = await importTransformers();
useLocalModels(transformers, root);

const { AutoTokenizer, CLIPTextModelWithProjection } = transformers;
const startedAt = performance.now();
const tokenizer = await AutoTokenizer.from_pretrained(CLIP_MODEL_ID);
const model = await CLIPTextModelWithProjection.from_pretrained(CLIP_MODEL_ID, { dtype: DTYPE });

async function embedPrompts(prompts) {
    const inputs = tokenizer(prompts, { padding: true, truncation: true });
    const output = await model(inputs);
    const dims = output.text_embeds.dims;
    const data = output.text_embeds.data;
    if (dims[0] !== prompts.length || dims[1] !== EMBEDDING_DIM) {
        throw new Error(`Unexpected text_embeds dims ${JSON.stringify(dims)} for ${prompts.length} prompt(s)`);
    }

    const averaged = new Array(EMBEDDING_DIM).fill(0);
    for (let row = 0; row < prompts.length; row++) {
        for (let column = 0; column < EMBEDDING_DIM; column++) {
            averaged[column] += data[row * EMBEDDING_DIM + column];
        }
    }
    for (let column = 0; column < EMBEDDING_DIM; column++) {
        averaged[column] /= prompts.length;
    }
    return normalize(averaged);
}

const animals = {};
for (const entry of animalPrompts) {
    animals[entry.key] = { prompts: entry.prompts, embedding: round(await embedPrompts(entry.prompts)) };
    console.log(`animal ${entry.key}: ${entry.prompts.length} prompt(s)`);
}

const varieties = {};
for (const entry of varietyPrompts) {
    varieties[entry.key] = { prompts: entry.prompts, embedding: round(await embedPrompts(entry.prompts)) };
    console.log(`variety ${entry.key}: ${entry.prompts.length} prompt(s)`);
}

const distractors = [];
for (const prompt of DISTRACTOR_PROMPTS) {
    distractors.push({ label: prompt.replace(/^a photo of (a |an )?/, ''), prompts: [prompt], embedding: round(await embedPrompts([prompt])) });
}
const outOfScope = [];
for (const prompt of OUT_OF_SCOPE_PROMPTS) {
    outOfScope.push({ label: prompt.replace(/^a photo of (a |an )?/, ''), prompts: [prompt], embedding: round(await embedPrompts([prompt])) });
}
console.log('distractors: ' + distractors.length + ', out_of_scope: ' + outOfScope.length);

const output = {
    version: OUTPUT_VERSION,
    model: CLIP_MODEL_ID,
    dtype: DTYPE,
    dim: EMBEDDING_DIM,
    animals,
    varieties,
    distractors,
    out_of_scope: outOfScope,
};

mkdirSync(dirname(outputPath), { recursive: true });
writeFileSync(outputPath, `${JSON.stringify(output)}\n`);

const size = statSync(outputPath).size;
console.log(`Wrote ${outputPath} (${(size / 1024).toFixed(1)} KiB) in ${Math.round(performance.now() - startedAt)} ms`);
