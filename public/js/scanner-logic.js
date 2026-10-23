/**
 * EcoSight scanner decision logic (pure module).
 *
 * Kept free of DOM, network, and TensorFlow.js dependencies so it can be
 * unit-tested with plain Node (see tests/js/scanner-logic.test.js) and used
 * by the browser scanner.
 *
 * API:
 *   boxAreaRatio(bbox, width, height)
 *   chooseCocoCandidate(predictions, width, height, config)
 *   reconcile(cocoCandidate, mobilenetTop, labelMap, config)        (legacy fallback)
 *   combineClipViews(views, weights)
 *   decideFromClip(animalScores, distractorScores, config)
 *   decideFromFallback(mobilenetTop, labelToAnimal, config)
 *   applyAgreement(primary, fallback, config)
 *   decideVariety(varietyScores, config)
 *   stabilize(previous, label, now)
 *   canSave(decision, userConfirmed, config)
 */
(function (global) {
    'use strict';

    /**
     * Every tunable number in one place. scanner.js reads these instead of
     * redefining them, and the ?debug=1 panel prints them for calibration.
     */
    const CONFIG = {
        // --- COCO-SSD / MobileNet fallback (legacy box rules) -------------
        // Minimum COCO-SSD confidence for a box to be a candidate at all.
        MIN_CONFIDENCE_COCO: 0.60,
        // Minimum MobileNet confidence for a whole-image library match.
        MIN_CONFIDENCE_MOBILENET: 0.40,
        // Boxes covering less than this fraction of the frame are ignored
        // (uploads get the stricter value; live camera frames the looser one).
        MIN_BOX_AREA_UPLOAD: 0.04,
        MIN_BOX_AREA_CAMERA: 0.03,
        // MobileNet probability needed to overrule a COCO candidate with a
        // different library animal, and to report an unknown top-1 class.
        STRONG_MOBILENET: 0.50,
        // Added to the displayed confidence when two signals agree
        // (the combined value is capped at 0.99).
        AGREE_BONUS: 0.10,
        // A box that touches the frame edge AND is thinner than this fraction
        // of the frame in either direction is ignored (clipped detections).
        EDGE_THIN_RATIO: 0.08,
        // COCO classes that get extra scrutiny: household animals are often
        // forced onto a neighbouring class by COCO-SSD.
        DOMESTIC_LABELS: ['cat', 'dog', 'horse', 'sheep', 'cow', 'bird'],
        // Camera mode runs the fallback model at most once per this many ms.
        MOBILENET_INTERVAL_MS: 1000,

        // --- CLIP zero-shot (primary recogniser) --------------------------
        // Auto-accept a CLIP result: top-1 probability at/above this...
        CLIP_ACCEPT: 0.55,
        // ...and at least this far ahead of the runner-up.
        CLIP_MARGIN: 0.15,
        // Below this CLIP probability nothing is reported (fallback decides).
        MIN_CONFIDENCE_CLIP: 0.50,
        // A close race: every candidate within this band of the top-1 is
        // offered to the user as "which one is it?" options.
        UNSURE_BAND: 0.20,
        // A user-confirmed result may be saved down to this confidence.
        MIN_CONFIDENCE_USER_CONFIRMED: 0.30,
        // Weighted mix of the four CLIP views (whole frame, 70% centre crop,
        // 50% centre crop, COCO box crop). Missing views are re-normalised
        // before combining, so the first three are used when there is no box.
        CLIP_WEIGHTS: [0.35, 0.25, 0.25, 0.15],
        // Softmax scaling for cosine similarities: probabilities are computed
        // as softmax(CLIP_LOGIT_SCALE * similarity).
        CLIP_LOGIT_SCALE: 100,
        // Camera mode runs CLIP at most once per this many milliseconds.
        CAMERA_CLIP_INTERVAL_MS: 1500,
        // Consecutive identical CLIP labels before the camera result is shown.
        CAMERA_STABLE_RUNS: 3,
        // Camera results are cleared after this many ms without a detection.
        RESULT_CLEAR_MS: 2000,

        // --- MobileNet group fallback + variety ---------------------------
        // Accept a fallback animal group only at/above this probability...
        DISTRACTOR_ACCEPT: 0.50,
        // ...and at least this far ahead of the next group (the margin that
        // keeps look-alike groups from silently winning).
        DISTRACTOR_MARGIN: 0.10,
        // Accept a variety (breed) only at/above this probability...
        VARIETY_ACCEPT: 0.45,
        // ...and at least this far ahead of the next variety.
        VARIETY_MARGIN: 0.10,
    };

    function boxAreaRatio(bbox, width, height) {
        if (!bbox || !width || !height) return 0;
        const w = Number(bbox[2]);
        const h = Number(bbox[3]);
        if (!Number.isFinite(w) || !Number.isFinite(h) || w < 0 || h < 0) return 0;
        return (w * h) / (width * height);
    }

    function isDomestic(cls, domesticLabels) {
        return (domesticLabels || CONFIG.DOMESTIC_LABELS).includes(cls);
    }

    /**
     * Normalize a class for agreement checks: lower-case, drop anything after
     * the first comma (ImageNet species suffixes like "Cavia cobaya"), and
     * strip non-alphanumeric characters. "red fox, Vulpes vulpes" -> "redfox".
     */
    function normalizeClass(cls) {
        return String(cls || '')
            .split(',')[0]
            .toLowerCase()
            .replace(/[^a-z0-9]/g, '');
    }

    /**
     * Map a raw MobileNet className to a library animal (coco_label) through
     * the labelMap built from animals.model_labels. Tries the exact
     * lower-cased class name first, then each comma-separated synonym.
     */
    function mapToLibrary(className, labelMap) {
        if (!className || !labelMap) return null;
        const direct = String(className).toLowerCase();
        if (labelMap[direct]) return labelMap[direct];
        const parts = String(className).split(',');
        for (let i = 0; i < parts.length; i++) {
            const key = parts[i].trim().toLowerCase();
            if (labelMap[key]) return labelMap[key];
        }
        return null;
    }

    function touchesEdge(bbox, width, height) {
        const x = Number(bbox[0]);
        const y = Number(bbox[1]);
        const w = Number(bbox[2]);
        const h = Number(bbox[3]);
        const tolerance = 1;
        return x <= tolerance || y <= tolerance || x + w >= width - tolerance || y + h >= height - tolerance;
    }

    /**
     * Pick the single best COCO-SSD candidate for a frame.
     *
     * A prediction survives only when it is a supported class, at or above
     * MIN_CONFIDENCE_COCO, at or above the minimum box area for this source
     * (uploads 0.04, camera 0.03), and not an edge-touching speck (a box that
     * touches the frame edge AND is thinner than 8% in either direction is a
     * clipped background detection).
     *
     * Survivors are ranked by score * sqrt(areaRatio) so a large box on the
     * real subject beats a high-score thumbnail in the corner.
     *
     * Returns { candidate, ignored } where candidate is null when nothing
     * survives, and ignored lists every dropped prediction with its reason
     * (used by the ?debug=1 panel and the low-confidence fallback).
     */
    function chooseCocoCandidate(predictions, width, height, config) {
        const options = Object.assign({}, CONFIG, config || {});
        const minArea = options.upload ? options.MIN_BOX_AREA_UPLOAD : options.MIN_BOX_AREA_CAMERA;
        const supported = options.supportedCoco instanceof Set
            ? options.supportedCoco
            : new Set(options.supportedCoco || []);
        const ignored = [];
        const survivors = [];

        (predictions || []).forEach(function (prediction) {
            const areaRatio = boxAreaRatio(prediction.bbox, width, height);
            const entry = {
                class: prediction.class,
                score: prediction.score,
                areaRatio: areaRatio,
                bbox: prediction.bbox,
                prediction: prediction,
            };
            let reason = null;
            if (!supported.has(prediction.class)) {
                reason = 'unsupported class';
            } else if (!(prediction.score >= options.MIN_CONFIDENCE_COCO)) {
                reason = 'below MIN_CONFIDENCE_COCO';
            } else if (!(areaRatio >= minArea)) {
                reason = 'box area below minimum';
            } else if (touchesEdge(prediction.bbox, width, height)
                && Math.min(prediction.bbox[2] / width, prediction.bbox[3] / height) < options.EDGE_THIN_RATIO) {
                reason = 'edge-touching and thin';
            }
            if (reason) {
                entry.reason = reason;
                ignored.push(entry);
            } else {
                survivors.push(entry);
            }
        });

        let candidate = null;
        if (survivors.length > 0) {
            survivors.sort(function (a, b) {
                return (b.score * Math.sqrt(b.areaRatio)) - (a.score * Math.sqrt(a.areaRatio));
            });
            const best = survivors[0];
            candidate = {
                class: best.class,
                score: Number(best.score),
                bbox: best.prediction.bbox,
                areaRatio: best.areaRatio,
                rankScore: best.score * Math.sqrt(best.areaRatio),
            };
            survivors.slice(1).forEach(function (entry) {
                ignored.push({ class: entry.class, score: entry.score, areaRatio: entry.areaRatio, bbox: entry.bbox, reason: 'kept but outranked' });
            });
        }

        ignored.sort(function (a, b) { return b.score - a.score; });
        return { candidate: candidate, ignored: ignored };
    }

    /**
     * Decide the final result from a COCO-SSD candidate and the MobileNet
     * top-5 predictions for the same frame.
     *
     * Rules, in priority order (the fired rule name is returned as `rule`):
     *  1. "agree"          — both models map to the same library animal:
     *                        accept the COCO box and add AGREE_BONUS to the
     *                        displayed confidence (capped at 0.99).
     *  2. "mobile-override"— a candidate exists, but MobileNet's top animal is
     *                        a *different* library animal at/above
     *                        STRONG_MOBILENET: trust MobileNet (whole-image,
     *                        no box).
     *  3. "uncertain"      — a domestic candidate (cat/dog/horse/sheep/cow/
     *                        bird) that none of MobileNet's top 5 support,
     *                        while MobileNet's top-1 is some non-library
     *                        class: report "Not sure what this is" with the
     *                        guesses instead of guessing. Not saveable.
     *  (candidate accepted when none of the above fire)
     *  4. "whole-image"    — no candidate, but a top-5 prediction maps to a
     *                        library animal at/above MIN_CONFIDENCE_MOBILENET.
     *  5. "not-in-library" — no candidate, top-1 at/above STRONG_MOBILENET but
     *                        not in the library: name the class, do not save.
     *  6. "low-confidence" / "none" — everything else; near-miss boxes become
     *                        the low-confidence state, otherwise nothing found.
     *
     * @param {object|null} cocoCandidate from chooseCocoCandidate()
     * @param {Array<{className:string, probability:number}>} mobilenetTop top 5
     * @param {Object<string,string>} labelMap lower-cased ImageNet class/synonym -> coco_label
     * @param {object} config optional CONFIG overrides plus supportedCoco,
     *        upload, and nearMisses (supported boxes below MIN_CONFIDENCE_COCO)
     */
    function reconcile(cocoCandidate, mobilenetTop, labelMap, config) {
        const options = Object.assign({}, CONFIG, config || {});
        const top = Array.isArray(mobilenetTop) ? mobilenetTop : (mobilenetTop ? [mobilenetTop] : []);
        const mapped = top.map(function (entry) {
            return {
                className: entry.className,
                probability: Number(entry.probability),
                animal: mapToLibrary(entry.className, labelMap),
            };
        });
        const first = mapped[0] || null;
        const wholeImageBox = (options.width && options.height)
            ? [0, 0, options.width, options.height]
            : [0, 0, 1, 1];

        if (cocoCandidate) {
            // Rule 1: both models agree on the same library animal.
            if (first && first.animal && normalizeClass(first.animal) === normalizeClass(cocoCandidate.class)) {
                return {
                    status: 'detected',
                    rule: 'agree',
                    label: cocoCandidate.class,
                    modelSource: 'coco-ssd',
                    confidence: Math.min(cocoCandidate.score + options.AGREE_BONUS, 0.99),
                    box: cocoCandidate.bbox,
                    wholeImage: false,
                };
            }

            // Rule 2: a different, confident library animal from MobileNet wins.
            if (first && first.animal && first.animal !== cocoCandidate.class
                && first.probability >= options.STRONG_MOBILENET) {
                return {
                    status: 'detected',
                    rule: 'mobile-override',
                    label: first.animal,
                    modelSource: 'mobilenet',
                    confidence: first.probability,
                    box: null,
                    wholeImage: true,
                };
            }

            // Rule 3: domestic guess that MobileNet's top 5 do not support and
            // whose top-1 is an unknown class -> uncertain, not a wrong answer.
            const supportedByTop = mapped.some(function (entry) {
                return entry.animal && normalizeClass(entry.animal) === normalizeClass(cocoCandidate.class);
            });
            if (isDomestic(cocoCandidate.class, options.DOMESTIC_LABELS)
                && first && !first.animal && !supportedByTop) {
                return {
                    status: 'uncertain',
                    rule: 'uncertain',
                    cocoGuess: cocoCandidate.class,
                    cocoConfidence: cocoCandidate.score,
                    guesses: mapped.slice(0, 3).map(function (entry) {
                        return { className: entry.className, probability: entry.probability };
                    }),
                    box: null,
                    wholeImage: false,
                };
            }

            // No rule overrode the candidate: the surviving box stands.
            return {
                status: 'detected',
                rule: 'coco-candidate',
                label: cocoCandidate.class,
                modelSource: 'coco-ssd',
                confidence: Number(cocoCandidate.score),
                box: cocoCandidate.bbox,
                wholeImage: false,
            };
        }

        // No COCO candidate: MobileNet decides.
        const libraryMatch = mapped.find(function (entry) {
            return entry.animal && entry.probability >= options.MIN_CONFIDENCE_MOBILENET;
        }) || null;

        // Rule 4: whole-image library match at/above the MobileNet threshold.
        if (libraryMatch) {
            return {
                status: 'detected',
                rule: 'whole-image',
                label: libraryMatch.animal,
                modelSource: 'mobilenet',
                confidence: libraryMatch.probability,
                box: wholeImageBox,
                wholeImage: true,
            };
        }

        // Rule 5: confident but unknown class -> name it, never save it.
        if (first && !first.animal && first.probability >= options.STRONG_MOBILENET) {
            return {
                status: 'not-in-library',
                rule: 'not-in-library',
                className: first.className,
                probability: first.probability,
                box: null,
                wholeImage: false,
            };
        }

        // Rule 6: near-miss boxes become low confidence; otherwise nothing.
        const nearMisses = (options.nearMisses || []).slice(0, 4);
        if (nearMisses.length > 0) {
            return {
                status: 'low-confidence',
                rule: 'low-confidence',
                best: { class: nearMisses[0].class, score: nearMisses[0].score },
                alternatives: nearMisses.slice(1).map(function (entry) {
                    return { class: entry.class, score: entry.score };
                }),
                box: null,
                wholeImage: false,
            };
        }

        return { status: 'none', rule: 'none', box: null, wholeImage: false };
    }

    // ---------------------------------------------------------------------
    // CLIP zero-shot primitives
    // ---------------------------------------------------------------------

    /**
     * Accept either an array of {label, probability} or a plain
     * {label: probability} map and return a descending [{label, probability}]
     * array with numeric probabilities.
     */
    function normalizeScores(scores) {
        if (!scores) return [];
        const list = Array.isArray(scores)
            ? scores
            : Object.keys(scores).map(function (label) {
                return { label: label, probability: scores[label] };
            });
        return list
            .map(function (entry) {
                return { label: entry.label, probability: Number(entry.probability) || 0 };
            })
            .sort(function (a, b) { return b.probability - a.probability; });
    }

    /**
     * Combine per-view CLIP probability vectors into one ranking.
     *
     * Camera mode captures up to three views (centre, left, right) weighted
     * 0.4 / 0.4 / 0.2. Missing views are dropped and the remaining weights are
     * re-normalised, so a single view is used at full weight.
     */
    function combineClipViews(views, weights) {
        const list = (views || []).filter(function (view) { return view != null; });
        if (list.length === 0) return [];

        const configured = Array.isArray(weights) && weights.length > 0 ? weights : CONFIG.CLIP_WEIGHTS;
        const used = list.map(function (view, index) {
            const weight = Number(configured[index]);
            return { scores: normalizeScores(view), weight: Number.isFinite(weight) && weight > 0 ? weight : 0 };
        });
        const totalWeight = used.reduce(function (sum, entry) { return sum + entry.weight; }, 0);

        const combined = new Map();
        used.forEach(function (entry) {
            const weight = totalWeight > 0 ? entry.weight / totalWeight : 1 / list.length;
            entry.scores.forEach(function (score) {
                combined.set(score.label, (combined.get(score.label) || 0) + score.probability * weight);
            });
        });

        return Array.from(combined.entries())
            .map(function (entry) { return { label: entry[0], probability: entry[1] }; })
            .sort(function (a, b) { return b.probability - a.probability; });
    }

    /**
     * Decide from CLIP zero-shot probabilities (already softmaxed per view).
     *
     * Rules, in priority order:
     *  1. "distractor"   — the best non-animal prompt (person, food, car,
     *                      furniture, empty scene...) beats the best animal
     *                      by DISTRACTOR_MARGIN and is at/above
     *                      DISTRACTOR_ACCEPT: there is probably no animal.
     *  2. "clip"         — top-1 at/above CLIP_ACCEPT with at least
     *                      CLIP_MARGIN over the runner-up: accept.
     *  3. "clip-unsure"  — a close race at/above MIN_CONFIDENCE_CLIP: return
     *                      up to three candidates within UNSURE_BAND so the
     *                      user can pick. Not saved without confirmation.
     *  4. "clip-weak"    — clear leader but below CLIP_ACCEPT: a low-confidence
     *                      result the user may confirm (saved down to
     *                      MIN_CONFIDENCE_USER_CONFIRMED).
     *  5. "clip-below-threshold" / "none" — nothing usable; the caller runs
     *                      the MobileNet group fallback.
     *
     * @param {Array|Object} animalScores per-animal probabilities
     * @param {Array|Object} distractorScores per-distractor probabilities
     * @param {object} [config]
     */
    function decideFromClip(animalScores, distractorScores, config) {
        const options = Object.assign({}, CONFIG, config || {});
        const animals = normalizeScores(animalScores);
        const distractors = normalizeScores(distractorScores);
        const best = animals[0] || null;
        const bestDistractor = distractors[0] || null;

        if (bestDistractor
            && bestDistractor.probability >= options.DISTRACTOR_ACCEPT
            && (!best || bestDistractor.probability - best.probability >= options.DISTRACTOR_MARGIN)) {
            return {
                status: 'distractor',
                rule: 'distractor',
                label: bestDistractor.label,
                confidence: bestDistractor.probability,
                box: null,
                wholeImage: true,
            };
        }

        if (!best) {
            return { status: 'none', rule: 'none', box: null, wholeImage: true };
        }

        const second = animals[1] || null;
        const margin = best.probability - (second ? second.probability : 0);
        const candidates = animals
            .filter(function (entry) { return best.probability - entry.probability <= options.UNSURE_BAND; })
            .slice(0, 3);

        if (best.probability >= options.CLIP_ACCEPT && margin >= options.CLIP_MARGIN) {
            return {
                status: 'detected',
                rule: 'clip',
                label: best.label,
                modelSource: 'clip',
                confidence: best.probability,
                margin: margin,
                candidates: candidates,
                box: null,
                wholeImage: true,
            };
        }

        if (best.probability >= options.MIN_CONFIDENCE_CLIP && margin < options.CLIP_MARGIN) {
            return {
                status: 'unsure',
                rule: 'clip-unsure',
                confidence: best.probability,
                candidates: candidates,
                box: null,
                wholeImage: true,
            };
        }

        if (best.probability >= options.MIN_CONFIDENCE_CLIP) {
            return {
                status: 'low-confidence',
                rule: 'clip-weak',
                label: best.label,
                modelSource: 'clip',
                confidence: best.probability,
                candidates: candidates,
                minSaveConfidence: options.MIN_CONFIDENCE_USER_CONFIRMED,
                box: null,
                wholeImage: true,
            };
        }

        return {
            status: 'none',
            rule: 'clip-below-threshold',
            label: best.label,
            confidence: best.probability,
            box: null,
            wholeImage: true,
        };
    }

    /**
     * Decide from the MobileNet group fallback (whole-image, no box).
     *
     * Every top-5 class is mapped to a library animal through the generated
     * ImageNet group map (public/data/imagenet-groups.json). Breeds collapse
     * into one group, so "Labrador retriever" and "golden retriever" both
     * count towards dog.
     *
     * Rules:
     *  1. "fallback"       — best group at/above DISTRACTOR_ACCEPT and
     *                        DISTRACTOR_MARGIN clear of the next group.
     *  2. "fallback-weak"  — best group at/above MIN_CONFIDENCE_MOBILENET:
     *                        low-confidence, confirmable by the user.
     *  3. "not-in-library" — no group, top-1 unknown at/above STRONG_MOBILENET:
     *                        name the class, never save it.
     *  4. "none"           — nothing.
     *
     * @param {Array<{className:string, probability:number}>} mobilenetTop
     * @param {Object<string,string>} labelToAnimal ImageNet label -> coco_label
     * @param {object} [config]
     */
    function decideFromFallback(mobilenetTop, labelToAnimal, config) {
        const options = Object.assign({}, CONFIG, config || {});
        const top = Array.isArray(mobilenetTop) ? mobilenetTop : (mobilenetTop ? [mobilenetTop] : []);
        const groups = new Map();
        let bestUnknown = null;

        top.forEach(function (entry) {
            const probability = Number(entry.probability) || 0;
            const animal = mapToLibrary(entry.className, labelToAnimal);
            if (!animal) {
                if (!bestUnknown || probability > bestUnknown.probability) {
                    bestUnknown = { className: entry.className, probability: probability };
                }
                return;
            }
            const current = groups.get(animal);
            if (!current || probability > current.probability) {
                groups.set(animal, { label: animal, probability: probability, className: entry.className });
            }
        });

        const ranked = Array.from(groups.values()).sort(function (a, b) {
            return b.probability - a.probability;
        });
        const best = ranked[0] || null;
        const second = ranked[1] || null;
        const margin = best ? best.probability - (second ? second.probability : 0) : 0;
        const box = (options.width && options.height) ? [0, 0, options.width, options.height] : [0, 0, 1, 1];

        if (best && best.probability >= options.DISTRACTOR_ACCEPT && margin >= options.DISTRACTOR_MARGIN) {
            return {
                status: 'detected',
                rule: 'fallback',
                label: best.label,
                modelSource: 'mobilenet',
                confidence: best.probability,
                candidates: ranked.slice(0, 3),
                box: box,
                wholeImage: true,
            };
        }

        if (best && best.probability >= options.MIN_CONFIDENCE_MOBILENET) {
            return {
                status: 'low-confidence',
                rule: 'fallback-weak',
                label: best.label,
                modelSource: 'mobilenet',
                confidence: best.probability,
                candidates: ranked.slice(0, 3),
                minSaveConfidence: options.MIN_CONFIDENCE_USER_CONFIRMED,
                box: box,
                wholeImage: true,
            };
        }

        if (bestUnknown && bestUnknown.probability >= options.STRONG_MOBILENET) {
            return {
                status: 'not-in-library',
                rule: 'not-in-library',
                className: bestUnknown.className,
                probability: bestUnknown.probability,
                box: null,
                wholeImage: false,
            };
        }

        return { status: 'none', rule: 'none', box: null, wholeImage: true };
    }

    /**
     * Merge a CLIP decision with the MobileNet fallback decision.
     *
     * AGREE_BONUS is applied only when both signals point at the same animal:
     *  - both detected  -> keep the primary result, add the bonus;
     *  - primary weak   -> if the fallback detected the same animal (or the
     *                      primary's top candidate), promote to detected and
     *                      add the bonus.
     * Otherwise the primary decision wins; the fallback is then only used if
     * the primary found nothing.
     */
    function applyAgreement(primary, fallback, config) {
        const options = Object.assign({}, CONFIG, config || {});
        const boosted = function (base, confidence) {
            return Math.min(Math.max(Number(base) || 0, Number(confidence) || 0) + options.AGREE_BONUS, 0.99);
        };

        if (!primary) return fallback || null;
        if (!fallback) return primary;

        const primaryLabel = primary.label
            || (primary.candidates && primary.candidates[0] ? primary.candidates[0].label : null);
        const fallbackLabel = fallback.label
            || (fallback.candidates && fallback.candidates[0] ? fallback.candidates[0].label : null);

        if (primary.status === 'detected' && fallback.status === 'detected'
            && primaryLabel && primaryLabel === fallbackLabel) {
            return Object.assign({}, primary, {
                rule: 'agree',
                confidence: boosted(primary.confidence, fallback.confidence),
            });
        }

        const primaryWeak = primary.status === 'low-confidence' || primary.status === 'unsure';
        if (primaryWeak && fallback.status === 'detected' && fallbackLabel
            && primaryLabel && primaryLabel === fallbackLabel) {
            return {
                status: 'detected',
                rule: 'agree',
                label: fallbackLabel,
                modelSource: primary.modelSource || fallback.modelSource,
                confidence: boosted(primary.confidence, fallback.confidence),
                candidates: primary.candidates || [],
                box: fallback.box || null,
                wholeImage: primary.wholeImage !== false,
            };
        }

        return primary;
    }

    /**
     * Decide an optional variety (breed) within an already-detected animal.
     *
     * Accept only at/above VARIETY_ACCEPT and VARIETY_MARGIN ahead of the
     * runner-up; otherwise report the near-misses for a "which breed?" prompt
     * without a decision. Varieties never change the animal result.
     *
     * @param {Array|Object} varietyScores per-variety probabilities
     * @param {object} [config]
     */
    function decideVariety(varietyScores, config) {
        const options = Object.assign({}, CONFIG, config || {});
        const ranked = normalizeScores(varietyScores).slice(0, 3);
        const best = ranked[0] || null;

        if (!best) {
            return { status: 'none', rule: 'variety-none', candidates: [] };
        }

        const second = ranked[1] || null;
        const margin = best.probability - (second ? second.probability : 0);

        if (best.probability >= options.VARIETY_ACCEPT && margin >= options.VARIETY_MARGIN) {
            return {
                status: 'matched',
                rule: 'variety',
                label: best.label,
                confidence: best.probability,
                candidates: ranked,
            };
        }

        return {
            status: 'none',
            rule: 'variety-weak',
            best: best.label,
            confidence: best.probability,
            candidates: ranked,
        };
    }

    /**
     * Camera-mode stability filter: require CAMERA_STABLE_RUNS consecutive
     * frames with the same label before showing a result. A different label
     * (or a null label) resets the counter.
     *
     * Pass the previous return value back in; `now` is only used to remember
     * when the current run started (RESULT_CLEAR_MS belongs to the caller).
     *
     * @param {{label:string, runs:number, firstSeenAt:number}|null} previous
     * @param {string|null} label
     * @param {number} [now]
     */
    function stabilize(previous, label, now) {
        const time = Number.isFinite(now) ? now : Date.now();
        if (!label) {
            return { label: null, runs: 0, stable: false, firstSeenAt: null };
        }
        const sameRun = previous && previous.label === label;
        const runs = (sameRun ? Number(previous.runs) || 0 : 0) + 1;
        const firstSeenAt = sameRun && Number.isFinite(previous.firstSeenAt) ? previous.firstSeenAt : time;

        return {
            label: label,
            runs: runs,
            stable: runs >= CONFIG.CAMERA_STABLE_RUNS,
            firstSeenAt: firstSeenAt,
        };
    }

    /**
     * Whether a result may be saved.
     *
     * Confident detections always may. Weak/unsure results may only be saved
     * after the user confirms, and only at/above
     * MIN_CONFIDENCE_USER_CONFIRMED. Distractors, not-in-library results and
     * empty results never are.
     *
     * @param {object|null} decision
     * @param {boolean} userConfirmed
     * @param {object} [config]
     */
    function canSave(decision, userConfirmed, config) {
        const options = Object.assign({}, CONFIG, config || {});
        if (!decision) return false;
        if (decision.status === 'detected') return true;
        if (!userConfirmed) return false;
        if (decision.status === 'low-confidence' || decision.status === 'unsure') {
            return Number(decision.confidence) >= options.MIN_CONFIDENCE_USER_CONFIRMED;
        }
        return false;
    }

    const EcoSightLogic = {
        CONFIG,
        DEFAULTS: CONFIG,
        boxAreaRatio,
        isDomestic,
        normalizeClass,
        mapToLibrary,
        chooseCocoCandidate,
        reconcile,
        normalizeScores,
        combineClipViews,
        decideFromClip,
        decideFromFallback,
        applyAgreement,
        decideVariety,
        stabilize,
        canSave,
    };

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = EcoSightLogic;
    }
    global.EcoSightLogic = EcoSightLogic;
})(typeof window !== 'undefined' ? window : globalThis);
