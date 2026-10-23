/**
 * Unit tests for public/js/scanner-logic.js (pure decision module).
 *
 * The logic file is a classic browser script that assigns to
 * `window.EcoSightLogic` and `module.exports`, so it is executed inside a vm
 * sandbox where `module.exports` exists (keeping the browser path untouched).
 * Run with `npm run test:js`.
 */
import { readFileSync } from 'node:fs';
import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const here = dirname(fileURLToPath(import.meta.url));
const source = readFileSync(join(here, '..', '..', 'public', 'js', 'scanner-logic.js'), 'utf8');

const hostModule = { exports: {} };
const sandbox = { module: hostModule };
vm.createContext(sandbox);
vm.runInContext(source, sandbox, { filename: 'scanner-logic.js' });
const Logic = hostModule.exports;

// Values returned from the vm sandbox carry that realm's prototypes, which
// node's deepStrictEqual rejects; round-trip them through JSON first.
const plain = (value) => JSON.parse(JSON.stringify(value));

const FRAME = { width: 1000, height: 1000 };
const box = (cls, score, bbox) => ({ class: cls, score, bbox });
const pick = (predictions, extra = {}) =>
    Logic.chooseCocoCandidate(predictions, FRAME.width, FRAME.height, {
        supportedCoco: ['dog', 'cat', 'elephant'],
        upload: true,
        ...extra,
    });
const decide = (candidate, mobileTop, extra = {}) =>
    Logic.reconcile(candidate, mobileTop, LABEL_MAP, {
        ...FRAME,
        upload: true,
        ...extra,
    });

// Lower-cased ImageNet class/synonym -> coco_label, mirroring the payload
// served by /scanner/labels (mobilenet_map).
const LABEL_MAP = {
    dog: 'dog',
    cat: 'cat',
    elephant: 'elephant',
    'guinea pig': 'guinea pig',
    'cavia cobaya': 'guinea pig',
    lion: 'lion',
    'panthera leo': 'lion',
    'red fox': 'red fox',
    'vulpes vulpes': 'red fox',
};

test('CONFIG exposes the approved CLIP/fallback constants', () => {
    assert.deepEqual(
        plain(Logic.CONFIG.CLIP_WEIGHTS),
        [0.35, 0.25, 0.25, 0.15]
    );
    const expected = {
        CLIP_ACCEPT: 0.55,
        CLIP_MARGIN: 0.15,
        MIN_CONFIDENCE_CLIP: 0.50,
        UNSURE_BAND: 0.20,
        MIN_CONFIDENCE_USER_CONFIRMED: 0.30,
        CLIP_LOGIT_SCALE: 100,
        CAMERA_CLIP_INTERVAL_MS: 1500,
        CAMERA_STABLE_RUNS: 3,
        RESULT_CLEAR_MS: 2000,
        DISTRACTOR_ACCEPT: 0.50,
        DISTRACTOR_MARGIN: 0.10,
        VARIETY_ACCEPT: 0.45,
        VARIETY_MARGIN: 0.10,
        AGREE_BONUS: 0.10,
        MIN_CONFIDENCE_COCO: 0.60,
        MIN_CONFIDENCE_MOBILENET: 0.40,
    };
    for (const [key, value] of Object.entries(expected)) {
        assert.equal(Logic.CONFIG[key], value, key);
    }
});

test('normalizeClass strips ImageNet species suffixes', () => {
    assert.equal(Logic.normalizeClass('red fox, Vulpes vulpes'), 'redfox');
    assert.equal(Logic.normalizeClass('Dog'), 'dog');
    assert.equal(Logic.normalizeClass(null), '');
});

test('mapToLibrary resolves full class names and comma synonyms', () => {
    assert.equal(Logic.mapToLibrary('guinea pig, Cavia cobaya', LABEL_MAP), 'guinea pig');
    assert.equal(Logic.mapToLibrary('LION, Panthera leo', LABEL_MAP), 'lion');
    assert.equal(Logic.mapToLibrary('starfish', LABEL_MAP), null);
    assert.equal(Logic.mapToLibrary(null, LABEL_MAP), null);
});

test('boxAreaRatio computes bbox fraction of the frame', () => {
    assert.equal(Logic.boxAreaRatio([0, 0, 500, 500], 1000, 1000), 0.25);
    assert.equal(Logic.boxAreaRatio([0, 0, 40, 40], 1000, 1000), 0.0016);
    assert.equal(Logic.boxAreaRatio(null, 1000, 1000), 0);
});

test('chooseCocoCandidate ignores boxes below the upload area minimum', () => {
    const { candidate, ignored } = pick([box('dog', 0.62, [0, 0, 40, 40])]);
    assert.equal(candidate, null);
    assert.equal(ignored.length, 1);
    assert.equal(ignored[0].reason, 'box area below minimum');
});

test('chooseCocoCandidate ignores edge-touching thin boxes', () => {
    const { candidate, ignored } = pick([box('dog', 0.9, [50, 0, 900, 50])]);
    assert.equal(candidate, null);
    assert.equal(ignored[0].reason, 'edge-touching and thin');
});

test('chooseCocoCandidate rejects unsupported and below-threshold boxes', () => {
    const { candidate, ignored } = pick([
        box('person', 0.99, [0, 0, 800, 800]),
        box('cat', 0.55, [0, 0, 800, 800]),
    ]);
    assert.equal(candidate, null);
    assert.deepEqual(
        plain(ignored.map((entry) => entry.reason)).sort(),
        ['below MIN_CONFIDENCE_COCO', 'unsupported class']
    );
});

test('chooseCocoCandidate ranks by score times sqrt of area', () => {
    const { candidate, ignored } = pick([
        box('cat', 0.95, [100, 100, 200, 200]),
        box('dog', 0.9, [100, 100, 600, 600]),
    ]);
    assert.equal(candidate.class, 'dog');
    assert.equal(ignored[0].reason, 'kept but outranked');
});

test('rule agree: both models on the same animal adds the bonus', () => {
    const { candidate } = pick([box('dog', 0.9, [100, 100, 600, 600])]);
    const winner = decide(candidate, [
        { className: 'dog', probability: 0.7 },
        { className: 'Labrador retriever', probability: 0.1 },
    ]);
    assert.equal(winner.rule, 'agree');
    assert.equal(winner.status, 'detected');
    assert.equal(winner.label, 'dog');
    assert.equal(winner.modelSource, 'coco-ssd');
    assert.equal(winner.confidence, 0.99);
    assert.equal(winner.wholeImage, false);
});

test('rule agree caps the combined confidence at 0.99', () => {
    const { candidate } = pick([box('dog', 0.97, [100, 100, 600, 600])]);
    const winner = decide(candidate, [{ className: 'dog', probability: 0.9 }]);
    assert.equal(winner.rule, 'agree');
    assert.equal(winner.confidence, 0.99);
});

test('rule mobile-override: a different confident library animal wins', () => {
    const { candidate } = pick([box('dog', 0.9, [100, 100, 600, 600])]);
    const winner = decide(candidate, [
        { className: 'red fox, Vulpes vulpes', probability: 0.55 },
        { className: 'kit fox, Vulpes macrotis', probability: 0.2 },
    ]);
    assert.equal(winner.rule, 'mobile-override');
    assert.equal(winner.status, 'detected');
    assert.equal(winner.label, 'red fox');
    assert.equal(winner.modelSource, 'mobilenet');
    assert.equal(winner.wholeImage, true);
    assert.equal(winner.box, null);
});

test('domestic candidate survives when MobileNet is below the strong threshold', () => {
    const { candidate } = pick([box('dog', 0.9, [100, 100, 600, 600])]);
    const winner = decide(candidate, [
        { className: 'red fox, Vulpes vulpes', probability: 0.45 },
        { className: 'tabby cat', probability: 0.3 },
    ]);
    assert.equal(winner.rule, 'coco-candidate');
    assert.equal(winner.label, 'dog');
    assert.equal(winner.modelSource, 'coco-ssd');
    assert.equal(winner.confidence, 0.9);
});

test('rule uncertain: unsupported domestic guess is reported as uncertain', () => {
    const { candidate } = pick([box('cat', 0.65, [100, 100, 600, 600])]);
    const winner = decide(candidate, [
        { className: 'coffee mug', probability: 0.4 },
        { className: 'space bar', probability: 0.2 },
        { className: 'computer keyboard', probability: 0.1 },
    ]);
    assert.equal(winner.rule, 'uncertain');
    assert.equal(winner.status, 'uncertain');
    assert.equal(winner.cocoGuess, 'cat');
    assert.equal(winner.guesses.length, 3);
    assert.equal(winner.box, null);
});

test('uncertain does not fire when top-5 supports the candidate elsewhere', () => {
    const { candidate } = pick([box('dog', 0.7, [100, 100, 600, 600])]);
    const winner = decide(candidate, [
        { className: 'coffee mug', probability: 0.5 },
        { className: 'dog', probability: 0.3 },
    ]);
    assert.equal(winner.rule, 'coco-candidate');
    assert.equal(winner.label, 'dog');
});

test('rule whole-image: no candidate, top-5 library match at the threshold', () => {
    const winner = decide(null, [
        { className: 'starfish', probability: 0.8 },
        { className: 'guinea pig, Cavia cobaya', probability: 0.7 },
    ]);
    assert.equal(winner.rule, 'whole-image');
    assert.equal(winner.status, 'detected');
    assert.equal(winner.label, 'guinea pig');
    assert.equal(winner.modelSource, 'mobilenet');
    assert.equal(winner.wholeImage, true);
    assert.deepEqual(plain(winner.box), [0, 0, 1000, 1000]);
});

test('rule whole-image falls back to MobileNet when the box was filtered', () => {
    const { candidate } = pick([box('dog', 0.62, [0, 0, 40, 40])]);
    assert.equal(candidate, null);
    const winner = decide(null, [{ className: 'guinea pig, Cavia cobaya', probability: 0.7 }]);
    assert.equal(winner.rule, 'whole-image');
    assert.equal(winner.label, 'guinea pig');
});

test('no candidate and MobileNet below threshold is still a whole-image match at 0.40', () => {
    const winner = decide(null, [{ className: 'lion, Panthera leo', probability: 0.55 }]);
    assert.equal(winner.rule, 'whole-image');
    assert.equal(winner.label, 'lion');
    assert.equal(winner.confidence, 0.55);
});

test('rule not-in-library: confident unknown class is named but not saved', () => {
    const winner = decide(null, [{ className: 'starfish', probability: 0.6 }]);
    assert.equal(winner.rule, 'not-in-library');
    assert.equal(winner.status, 'not-in-library');
    assert.equal(winner.className, 'starfish');
    assert.equal(winner.probability, 0.6);
    assert.equal(winner.box, null);
});

test('rule low-confidence: near-miss boxes carry the best guess', () => {
    const winner = decide(null, [{ className: 'starfish', probability: 0.3 }], {
        nearMisses: [
            { class: 'dog', score: 0.55 },
            { class: 'cat', score: 0.5 },
        ],
    });
    assert.equal(winner.rule, 'low-confidence');
    assert.equal(winner.status, 'low-confidence');
    assert.deepEqual(plain(winner.best), { class: 'dog', score: 0.55 });
    assert.equal(winner.alternatives.length, 1);
});

test('rule none: nothing found at all', () => {
    const winner = decide(null, [{ className: 'starfish', probability: 0.3 }]);
    assert.equal(winner.rule, 'none');
    assert.equal(winner.status, 'none');
    assert.equal(winner.box, null);
});

test('reconcile tolerates a missing MobileNet result (model failed to load)', () => {
    const { candidate } = pick([box('elephant', 0.9, [100, 100, 600, 600])]);
    const winner = decide(candidate, null);
    assert.equal(winner.rule, 'coco-candidate');
    assert.equal(winner.label, 'elephant');
});

// ---------------------------------------------------------------------------
// CLIP multi-view + decision rules
// ---------------------------------------------------------------------------

test('combineClipViews uses the configured four-view weights and re-normalises', () => {
    const combined = Logic.combineClipViews([
        { dog: 1, cat: 0 },
        { dog: 0, cat: 1 },
        { dog: 0.5, cat: 0.5 },
    ]);
    const byLabel = Object.fromEntries(combined.map((entry) => [entry.label, entry.probability]));
    assert.ok(Math.abs(byLabel.dog - 0.5588235294117647) < 1e-9);
    assert.ok(Math.abs(byLabel.cat - 0.4411764705882353) < 1e-9);
    assert.equal(combined[0].label, 'dog');
});

test('combineClipViews gives a single view full weight', () => {
    const combined = Logic.combineClipViews([{ lion: 0.8, tiger: 0.2 }]);
    assert.deepEqual(plain(combined), [
        { label: 'lion', probability: 0.8 },
        { label: 'tiger', probability: 0.2 },
    ]);
});

test('combineClipViews returns [] when there are no views', () => {
    assert.deepEqual(plain(Logic.combineClipViews([])), []);
    assert.deepEqual(plain(Logic.combineClipViews(null)), []);
});

test('decideFromClip accepts a confident, clear winner', () => {
    const decision = Logic.decideFromClip(
        [{ label: 'dog', probability: 0.62 }, { label: 'cat', probability: 0.3 }],
        []
    );
    assert.equal(decision.status, 'detected');
    assert.equal(decision.rule, 'clip');
    assert.equal(decision.label, 'dog');
    assert.equal(decision.modelSource, 'clip');
    assert.equal(decision.confidence, 0.62);
    assert.equal(decision.wholeImage, true);
});

test('decideFromClip asks when the race is close', () => {
    const decision = Logic.decideFromClip(
        [{ label: 'dog', probability: 0.6 }, { label: 'cat', probability: 0.5 }],
        []
    );
    assert.equal(decision.status, 'unsure');
    assert.equal(decision.rule, 'clip-unsure');
    assert.deepEqual(plain(decision.candidates.map((entry) => entry.label)), ['dog', 'cat']);
});

test('decideFromClip returns a confirmable weak result', () => {
    const decision = Logic.decideFromClip(
        [{ label: 'tiger', probability: 0.52 }, { label: 'lion', probability: 0.2 }],
        []
    );
    assert.equal(decision.status, 'low-confidence');
    assert.equal(decision.rule, 'clip-weak');
    assert.equal(decision.label, 'tiger');
    assert.equal(decision.minSaveConfidence, 0.3);
});

test('decideFromClip falls back when below MIN_CONFIDENCE_CLIP', () => {
    const decision = Logic.decideFromClip(
        [{ label: 'dog', probability: 0.45 }, { label: 'cat', probability: 0.1 }],
        []
    );
    assert.equal(decision.status, 'none');
    assert.equal(decision.rule, 'clip-below-threshold');
});

test('decideFromClip reports a confident non-animal scene as distractor', () => {
    const decision = Logic.decideFromClip(
        [{ label: 'dog', probability: 0.45 }],
        [{ label: 'empty landscape', probability: 0.6 }]
    );
    assert.equal(decision.status, 'distractor');
    assert.equal(decision.rule, 'distractor');
});

test('decideFromClip ignores a distractor that does not clear the margin', () => {
    const decision = Logic.decideFromClip(
        [{ label: 'dog', probability: 0.5 }],
        [{ label: 'person', probability: 0.55 }]
    );
    assert.notEqual(decision.status, 'distractor');
    assert.equal(decision.rule, 'clip-weak');
});

// ---------------------------------------------------------------------------
// MobileNet group fallback
// ---------------------------------------------------------------------------

// Subset of public/data/imagenet-groups.json (ImageNet label -> animal).
const GROUP_MAP = {
    'labrador retriever': 'dog',
    'golden retriever': 'dog',
    'german shepherd, german shepherd dog, german police dog, alsatian': 'dog',
    'tabby, tabby cat': 'cat',
    'persian cat': 'cat',
    'african elephant, loxodonta africana': 'elephant',
};

test('decideFromFallback collapses breeds into one animal group', () => {
    const decision = Logic.decideFromFallback([
        { className: 'Labrador retriever', probability: 0.55 },
        { className: 'golden retriever', probability: 0.25 },
        { className: 'tabby, tabby cat', probability: 0.2 },
    ], GROUP_MAP);
    assert.equal(decision.status, 'detected');
    assert.equal(decision.rule, 'fallback');
    assert.equal(decision.label, 'dog');
    assert.equal(decision.confidence, 0.55);
});

test('decideFromFallback rejects groups below DISTRACTOR_ACCEPT as detected but keeps them confirmable', () => {
    const decision = Logic.decideFromFallback([
        { className: 'German shepherd, German shepherd dog, German police dog, alsatian', probability: 0.45 },
        { className: 'Persian cat', probability: 0.4 },
    ], GROUP_MAP);
    assert.equal(decision.status, 'low-confidence');
    assert.equal(decision.rule, 'fallback-weak');
    assert.equal(decision.label, 'dog');
    assert.equal(decision.minSaveConfidence, 0.3);
});

test('decideFromFallback names a confident class outside the library', () => {
    const decision = Logic.decideFromFallback([
        { className: 'starfish', probability: 0.62 },
    ], GROUP_MAP);
    assert.equal(decision.status, 'not-in-library');
    assert.equal(decision.className, 'starfish');
});

test('decideFromFallback finds nothing below MIN_CONFIDENCE_MOBILENET', () => {
    const decision = Logic.decideFromFallback([
        { className: 'Persian cat', probability: 0.35 },
    ], GROUP_MAP);
    assert.equal(decision.status, 'none');
});

// ---------------------------------------------------------------------------
// Agreement, variety, stability, saving
// ---------------------------------------------------------------------------

const clipDetected = (label, confidence) => ({
    status: 'detected',
    rule: 'clip',
    label,
    modelSource: 'clip',
    confidence,
    candidates: [],
    box: null,
    wholeImage: true,
});
const fallbackDetected = (label, confidence) => ({
    status: 'detected',
    rule: 'fallback',
    label,
    modelSource: 'mobilenet',
    confidence,
    candidates: [],
    box: [0, 0, 1, 1],
    wholeImage: true,
});

test('applyAgreement boosts two agreeing detections', () => {
    const merged = Logic.applyAgreement(clipDetected('dog', 0.6), fallbackDetected('dog', 0.55));
    assert.equal(merged.rule, 'agree');
    assert.equal(merged.label, 'dog');
    assert.ok(Math.abs(merged.confidence - 0.7) < 1e-9);
});

test('applyAgreement promotes a weak CLIP result confirmed by the fallback', () => {
    const weak = {
        status: 'low-confidence',
        rule: 'clip-weak',
        label: 'dog',
        modelSource: 'clip',
        confidence: 0.52,
        candidates: [{ label: 'dog', probability: 0.52 }],
        box: null,
        wholeImage: true,
    };
    const merged = Logic.applyAgreement(weak, fallbackDetected('dog', 0.5));
    assert.equal(merged.status, 'detected');
    assert.equal(merged.rule, 'agree');
    assert.ok(Math.abs(merged.confidence - 0.62) < 1e-9);
});

test('applyAgreement keeps the primary result when the signals disagree', () => {
    const primary = clipDetected('dog', 0.6);
    const merged = Logic.applyAgreement(primary, fallbackDetected('cat', 0.7));
    assert.equal(merged, primary);
});

test('applyAgreement falls back to whichever decision exists', () => {
    const fallback = fallbackDetected('cat', 0.7);
    assert.equal(Logic.applyAgreement(null, fallback), fallback);
    const primary = clipDetected('dog', 0.6);
    assert.equal(Logic.applyAgreement(primary, null), primary);
});

test('decideVariety accepts a clear breed and rejects a close race', () => {
    const matched = Logic.decideVariety({ Beagle: 0.5, Poodle: 0.3 });
    assert.equal(matched.status, 'matched');
    assert.equal(matched.label, 'Beagle');

    const weak = Logic.decideVariety({ Beagle: 0.44, Poodle: 0.4 });
    assert.equal(weak.status, 'none');
    assert.equal(weak.rule, 'variety-weak');

    const close = Logic.decideVariety({ Beagle: 0.5, Poodle: 0.45 });
    assert.equal(close.status, 'none');
});

test('stabilize needs CAMERA_STABLE_RUNS frames of the same label', () => {
    let state = Logic.stabilize(null, 'dog', 1000);
    assert.equal(state.runs, 1);
    assert.equal(state.stable, false);
    state = Logic.stabilize(state, 'dog', 2000);
    assert.equal(state.runs, 2);
    assert.equal(state.stable, false);
    state = Logic.stabilize(state, 'dog', 3000);
    assert.equal(state.runs, 3);
    assert.equal(state.stable, true);
    assert.equal(state.firstSeenAt, 1000);

    const reset = Logic.stabilize(state, 'cat', 4000);
    assert.equal(reset.runs, 1);
    assert.equal(reset.stable, false);

    const cleared = Logic.stabilize(state, null, 5000);
    assert.equal(cleared.label, null);
    assert.equal(cleared.runs, 0);
});

test('canSave only allows confirmed weak results at/above the user threshold', () => {
    const weak = { status: 'low-confidence', confidence: 0.4 };
    assert.equal(Logic.canSave(clipDetected('dog', 0.6), false), true);
    assert.equal(Logic.canSave(weak, false), false);
    assert.equal(Logic.canSave(weak, true), true);
    assert.equal(Logic.canSave({ status: 'low-confidence', confidence: 0.29 }, true), false);
    assert.equal(Logic.canSave({ status: 'distractor', confidence: 0.9 }, true), false);
    assert.equal(Logic.canSave({ status: 'not-in-library', probability: 0.9 }, true), false);
    assert.equal(Logic.canSave(null, true), false);
});

