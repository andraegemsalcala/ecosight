/**
 * Unit tests for public/js/open-vocab-core.js (pure open-vocabulary module).
 *
 * The logic file is a classic browser script that assigns to
 * `window.EcoSightOpenVocab` and `module.exports`, so it is executed inside a
 * vm sandbox where `module.exports` exists (keeping the browser path
 * untouched). Run with `npm run test:js`.
 */
import { readFileSync } from 'node:fs';
import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const here = dirname(fileURLToPath(import.meta.url));
const source = readFileSync(join(here, '..', '..', 'public', 'js', 'open-vocab-core.js'), 'utf8');

const hostModule = { exports: {} };
const sandbox = { module: hostModule };
vm.createContext(sandbox);
vm.runInContext(source, sandbox, { filename: 'open-vocab-core.js' });
const Vocab = hostModule.exports;

const plain = (value) => JSON.parse(JSON.stringify(value));

/** A unit-ish vector so cosine similarities are predictable. */
function unitVector(values) {
    const norm = Math.sqrt(values.reduce((sum, value) => sum + value * value, 0));
    return values.map((value) => value / norm);
}

const ROBIN = {
    qid: 'Q1234',
    common_name: 'European robin',
    scientific_name: 'Erithacus rubecula',
    group: 'birds',
    group_label: 'Birds',
    group_noun: 'Bird',
    name_missing: false,
    iucn_status: 'Least Concern',
};

const BLUE_TIT = {
    qid: 'Q5678',
    common_name: 'Blue tit',
    scientific_name: 'Cyanistes caeruleus',
    group: 'birds',
    group_noun: 'Bird',
    name_missing: false,
    iucn_status: 'Least Concern',
};

const FOX = {
    qid: 'Q9999',
    common_name: 'Red fox',
    scientific_name: 'Vulpes vulpes',
    group: 'mammals',
    group_noun: 'Mammal',
    name_missing: false,
    iucn_status: 'Least Concern',
};

const LIZARD = {
    qid: 'Q1111',
    common_name: 'Common lizard',
    scientific_name: 'Zootoca vivipara',
    group: 'reptiles',
    group_noun: 'Reptile',
    name_missing: false,
    iucn_status: 'Least Concern',
};

const UNNAMED = {
    qid: 'Q2222',
    common_name: 'Zootoca quadrilocularis',
    scientific_name: 'Zootoca quadrilocularis',
    group: 'reptiles',
    group_noun: 'Reptile',
    name_missing: true,
    iucn_status: 'Data Deficient',
};

test('halfToFloat decodes IEEE-754 half precision', () => {
    assert.equal(Vocab.halfToFloat(0x0000), 0);
    assert.equal(Vocab.halfToFloat(0x3c00), 1);
    assert.equal(Vocab.halfToFloat(0xc000), -2);
    assert.ok(Math.abs(Vocab.halfToFloat(0x3555) - 0.333251953125) < 1e-9);
});

test('decodeFloat16 reads a row-major float16 matrix', () => {
    const values = new Float32Array([1, 2, 3, 4]);
    const half = new Uint16Array(values.length);
    for (let i = 0; i < values.length; i++) {
        // Encode with the DataView trick supported by Node.
        half[i] = floatToHalf(values[i]);
    }
    const rows = Vocab.decodeFloat16(half, 2, 2);
    assert.equal(rows.length, 2);
    assert.ok(Math.abs(rows[0][0] - 1) < 1e-3);
    assert.ok(Math.abs(rows[1][1] - 4) < 1e-3);
});

test('decodeInt8 dequantises with the per-row scale', () => {
    const buffer = new Int8Array([100, -50, 25, 0]);
    const rows = Vocab.decodeInt8(buffer, [0.01, 0.02], 2, 2);
    assert.ok(Math.abs(rows[0][0] - 1) < 1e-9);
    assert.ok(Math.abs(rows[0][1] - -0.5) < 1e-9);
    assert.ok(Math.abs(rows[1][0] - 0.5) < 1e-9);
    assert.ok(Math.abs(rows[1][1] - 0) < 1e-9);
});

test('int8 round trip stays within the quantisation tolerance', () => {
    const original = unitVector([0.3, -0.5, 0.2, 0.9, -0.1]);
    const max = Math.max(...original.map((value) => Math.abs(value)));
    const scale = max / 127;
    const buffer = new Int8Array(original.map((value) => Math.round(value / scale)));
    const [decoded] = Vocab.decodeInt8(buffer, [scale], 1, original.length);
    for (let i = 0; i < original.length; i++) {
        assert.ok(Math.abs(decoded[i] - original[i]) <= scale / 2 + 1e-9);
    }
    assert.ok(Vocab.cosine(decoded, original) > 0.999);
});

test('cosine and dot compute the expected similarities', () => {
    assert.equal(Vocab.cosine([1, 0], [1, 0]), 1);
    assert.equal(Vocab.cosine([1, 0], [0, 1]), 0);
    assert.equal(Vocab.cosine([1, 0], [-1, 0]), -1);
    assert.equal(Vocab.dot([1, 2, 3], [4, 5, 6]), 32);
});

test('softmax returns a probability distribution', () => {
    const probabilities = Vocab.softmax([0.2, 0.1, 0.0], 100);
    const total = probabilities.reduce((sum, value) => sum + value, 0);
    assert.ok(Math.abs(total - 1) < 1e-9);
    assert.ok(probabilities[0] > probabilities[1]);
    assert.ok(probabilities[1] > probabilities[2]);
});

test('scoreVocabulary ranks the closest species first', () => {
    const matrix = [unitVector([1, 0]), unitVector([0, 1]), unitVector([0.9, 0.1])];
    const entries = [ROBIN, BLUE_TIT, FOX];
    const results = Vocab.scoreVocabulary(unitVector([1, 0]), matrix, entries, { shortlist: 3, OPEN_TOP_K: 3 });
    assert.equal(results[0].entry.qid, ROBIN.qid);
    assert.equal(results[1].entry.qid, FOX.qid);
    assert.ok(results[0].probability > results[1].probability);
});

test('decideTier1 accepts a clear leader', () => {
    const decision = Vocab.decideTier1([
        { probability: 0.42, entry: ROBIN },
        { probability: 0.20, entry: BLUE_TIT },
    ]);
    assert.equal(decision.status, 'matched');
    assert.equal(decision.rule, 'tier1-match');
    assert.equal(decision.candidates.length, 2);
});

test('decideTier1 requires a margin over the runner-up', () => {
    const decision = Vocab.decideTier1([
        { probability: 0.30, entry: ROBIN },
        { probability: 0.27, entry: BLUE_TIT },
    ]);
    assert.equal(decision.status, 'candidates');
    assert.equal(decision.rule, 'tier1-weak');
});

test('decideTier1 below the floor only leaves the extended list', () => {
    const decision = Vocab.decideTier1([
        { probability: 0.11, entry: ROBIN },
        { probability: 0.09, entry: BLUE_TIT },
    ]);
    assert.equal(decision.status, 'weak');
    assert.equal(decision.candidates.length, 0);
});

test('decideTier1 handles an empty vocabulary', () => {
    const decision = Vocab.decideTier1([]);
    assert.equal(decision.status, 'none');
});

test('decideTier2 is stricter than tier 1', () => {
    const tier1 = Vocab.decideTier1([
        { probability: 0.30, entry: ROBIN },
        { probability: 0.15, entry: BLUE_TIT },
    ]);
    const tier2 = Vocab.decideTier2([
        { probability: 0.30, entry: ROBIN },
        { probability: 0.15, entry: BLUE_TIT },
    ]);
    assert.equal(tier1.status, 'matched');
    assert.equal(tier2.status, 'candidates');
    assert.equal(tier2.requiresConfirmation, true);
});

test('decideTier2 never auto-accepts even with a strong lead', () => {
    const decision = Vocab.decideTier2([
        { probability: 0.80, entry: ROBIN },
        { probability: 0.10, entry: BLUE_TIT },
    ]);
    assert.equal(decision.status, 'candidates');
    assert.equal(decision.requiresConfirmation, true);
    assert.equal(decision.canAdd, undefined);
});

test('decideOpenVocab refuses an out-of-scope animal', () => {
    const decision = Vocab.decideOpenVocab({
        tier1: [{ probability: 0.20, entry: ROBIN }],
        outOfScope: [{ probability: 0.75, label: 'fish' }],
    });
    assert.equal(decision.status, 'out-of-scope');
    assert.equal(decision.canAdd, false);
    assert.equal(decision.canSave, false);
    assert.equal(decision.candidates.length, 0);
});

test('decideOpenVocab does not refuse when the in-scope species leads', () => {
    const decision = Vocab.decideOpenVocab({
        tier1: [{ probability: 0.70, entry: ROBIN }],
        outOfScope: [{ probability: 0.30, label: 'fish' }],
    });
    assert.notEqual(decision.status, 'out-of-scope');
    assert.equal(decision.status, 'matched');
});

test('decideOpenVocab offers the extended list when tier 1 is weak', () => {
    const decision = Vocab.decideOpenVocab({
        tier1: [{ probability: 0.13, entry: ROBIN }],
        outOfScope: [],
    });
    assert.equal(decision.status, 'candidates');
    assert.equal(decision.offerExtended, true);
    assert.equal(decision.canSave, false);
});

test('decideOpenVocab keeps a confident tier 1 match ahead of tier 2', () => {
    const decision = Vocab.decideOpenVocab({
        tier1: [{ probability: 0.40, entry: ROBIN }],
        tier2: [{ probability: 0.50, entry: BLUE_TIT }],
        outOfScope: [],
    });
    assert.equal(decision.tier, 1);
    assert.equal(decision.status, 'matched');
    assert.equal(decision.candidates[0].entry.common_name, 'European robin');
    assert.equal(decision.canAdd, true);
    assert.equal(decision.canSave, false);
});

test('combineCandidates ranks tier 1 and tier 2 by similarity', () => {
    const combined = Vocab.combineCandidates(
        [{ probability: 0.20, similarity: 0.30, entry: ROBIN }],
        [{ probability: 0.18, similarity: 0.40, entry: BLUE_TIT }],
        5
    );
    assert.equal(combined[0].entry.common_name, 'Blue tit');
    assert.equal(combined[0].tier, 2);
    assert.equal(combined[1].tier, 1);
});

test('matchAddAsType links a species to the library animal of the same genus', () => {
    const match = Vocab.matchAddAsType(FOX, [
        { common_name: 'Fox', scientific_name: 'Vulpes vulpes', coco_label: 'fox' },
        { common_name: 'Dog', scientific_name: 'Canis lupus familiaris', coco_label: 'dog' },
    ]);
    assert.equal(match.coco_label, 'fox');
});

test('matchAddAsType returns null for an unrelated species', () => {
    const match = Vocab.matchAddAsType(LIZARD, [
        { common_name: 'Fox', scientific_name: 'Vulpes vulpes', coco_label: 'fox' },
        { common_name: 'Dog', scientific_name: 'Canis lupus familiaris', coco_label: 'dog' },
    ]);
    assert.equal(match, null);
});

test('matchAddAsType falls back to the common name', () => {
    const match = Vocab.matchAddAsType(
        { common_name: 'Dog', scientific_name: 'Canis anthonyi', group: 'mammals' },
        [{ common_name: 'Dog', scientific_name: 'Canis lupus familiaris', coco_label: 'dog' }]
    );
    assert.equal(match.coco_label, 'dog');
});

test('displayName falls back to the scientific name when no common name is known', () => {
    assert.equal(Vocab.displayName(ROBIN), 'European robin');
    assert.equal(Vocab.displayName(UNNAMED), 'Zootoca quadrilocularis (Reptile)');
});

test('genusOf extracts the genus from a binomial name', () => {
    assert.equal(Vocab.genusOf('Erithacus rubecula'), 'erithacus');
    assert.equal(Vocab.genusOf('Vulpes'), '');
});

test('the exported configuration matches the documented thresholds', () => {
    assert.equal(Vocab.CONFIG.OPEN_MIN_PROB, 0.25);
    assert.equal(Vocab.CONFIG.OPEN_MARGIN, 0.08);
    assert.equal(Vocab.CONFIG.OPEN_TOP_K, 5);
    assert.equal(Vocab.CONFIG.CANDIDATE_FLOOR, 0.12);
    assert.equal(Vocab.CONFIG.EXTENDED_MIN_PROB, 0.35);
    assert.equal(Vocab.CONFIG.EXTENDED_MARGIN, 0.12);
    assert.equal(Vocab.CONFIG.ADD_AS_TYPE_KIND, 'species');
});

/** Minimal IEEE-754 float32 -> float16 encoder used to build the test data. */
function floatToHalf(value) {
    const floatView = new Float32Array(1);
    const intView = new Int32Array(floatView.buffer);
    floatView[0] = value;
    const x = intView[0];
    const sign = (x >> 16) & 0x8000;
    let exponent = ((x >> 23) & 0xff) - 127 + 15;
    let mantissa = x & 0x7fffff;

    if (exponent <= 0) {
        if (exponent < -10) return sign;
        mantissa = (mantissa | 0x800000) >> (1 - exponent);
        return sign | ((mantissa + 0x1000) >> 13);
    }
    if (exponent >= 31) return sign | 0x7c00 | (mantissa ? 0x0200 : 0);
    return sign | (exponent << 10) | ((mantissa + 0x1000) >> 13);
}


