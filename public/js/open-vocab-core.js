/**
 * EcoSight open-vocabulary matching (pure module).
 *
 * The library can only recognise the animals it already knows. This module
 * scores a photo against a much larger vocabulary of species so the scanner can
 * say "this is probably a European robin" instead of "not in the library".
 *
 * It is deliberately free of DOM, network and storage dependencies so it can
 * be unit-tested with plain Node (see tests/js/open-vocab.test.js).
 *
 * Scoring model
 * -------------
 * The vocabulary ships as a row-major matrix of L2-normalised CLIP text
 * embeddings (Float16 for tier 1, int8 with a per-row scale for tier 2). The
 * image embedding is normalised too, so a plain dot product is the cosine
 * similarity.
 *
 * A raw cosine over 20 000 species is not a probability, so the module
 * shortlists the most similar rows, then applies a softmax over that
 * shortlist. The resulting probability is "how much of the shortlist mass
 * does this candidate hold", which is a well-behaved 0..1 number regardless of
 * how large the vocabulary is.
 *
 * API:
 *   halfToFloat(half)
 *   decodeFloat16(buffer, count, dim)
 *   decodeInt8(buffer, scales, count, dim)
 *   dot(a, b)
 *   cosine(a, b)
 *   softmax(values, scale)
 *   scoreVocabulary(vector, matrix, count, dim, options)
 *   decideTier1(candidates, config)
 *   decideTier2(candidates, config)
 *   decideOpenVocab(input, config)
 *   matchAddAsType(candidate, libraryAnimals)
 *   displayName(entry)
 */
(function (global) {
    'use strict';

    const CONFIG = {
        // A tier-1 candidate is only trusted when it holds at least this much
        // of the shortlist mass...
        OPEN_MIN_PROB: 0.25,
        // ...and leads the runner-up by at least this much.
        OPEN_MARGIN: 0.08,
        // How many candidates are offered to the user.
        OPEN_TOP_K: 5,
        // Below this probability nothing is worth showing: the only action
        // left is the extended (tier 2) list.
        CANDIDATE_FLOOR: 0.12,
        // The extended list is stricter: a higher probability and a bigger
        // margin, because it covers far more species.
        EXTENDED_MIN_PROB: 0.35,
        EXTENDED_MARGIN: 0.12,
        // Number of rows that take part in the softmax.
        SHORTLIST: 20,
        // Softmax scale applied to the cosine similarities.
        SCALE: 100,
        // An out-of-scope animal (fish, marine mammal, ...) ends the flow:
        // no candidates, no add, no save.
        OUT_OF_SCOPE_ACCEPT: 0.50,
        OUT_OF_SCOPE_MARGIN: 0.10,
        // Only species whose genus matches a library animal may be added as a
        // type of that animal.
        ADD_AS_TYPE_KIND: 'species',
    };

    /**
     * Convert an IEEE-754 half-precision bit pattern to a JavaScript number.
     *
     * @param {number} half 16-bit unsigned value
     * @returns {number}
     */
    function halfToFloat(half) {
        const bits = Number(half) & 0xffff;
        const sign = (bits & 0x8000) ? -1 : 1;
        const exponent = (bits >> 10) & 0x1f;
        const fraction = bits & 0x3ff;

        if (exponent === 0) {
            return sign * Math.pow(2, -14) * (fraction / 1024);
        }
        if (exponent === 0x1f) {
            return fraction ? NaN : sign * Infinity;
        }
        return sign * Math.pow(2, exponent - 15) * (1 + fraction / 1024);
    }

    /**
     * Whether a value is already a typed-array view of the given element size.
     * Duck typing instead of `instanceof` keeps the check working across
     * realms (the browser worker, a Node vm sandbox and the page itself).
     *
     * @param {*} value
     * @param {number} bytesPerElement
     * @returns {boolean}
     */
    function isBinaryView(value, bytesPerElement) {
        return !! value
            && typeof value.length === 'number'
            && value.BYTES_PER_ELEMENT === bytesPerElement
            && typeof value.byteLength === 'number';
    }

    /**
     * Decode a row-major Float16 matrix into an array of Float32Array rows.
     *
     * @param {Uint16Array|ArrayBuffer} buffer
     * @param {number} count number of rows
     * @param {number} dim values per row
     * @returns {Float32Array[]}
     */
    function decodeFloat16(buffer, count, dim) {
        const view = isBinaryView(buffer, 2)
            ? buffer
            : new Uint16Array(buffer.buffer || buffer, buffer.byteOffset || 0, count * dim);

        const rows = [];
        for (let row = 0; row < count; row++) {
            const values = new Float32Array(dim);
            const base = row * dim;
            for (let column = 0; column < dim; column++) {
                values[column] = halfToFloat(view[base + column]);
            }
            rows.push(values);
        }
        return rows;
    }

    /**
     * Decode a row-major int8 matrix. Each row was quantised with its own
     * scale, so the original value is `quantised * scale`.
     *
     * @param {Int8Array|ArrayBuffer} buffer
     * @param {number[]|Float32Array} scales one scale per row
     * @param {number} count number of rows
     * @param {number} dim values per row
     * @returns {Float32Array[]}
     */
    function decodeInt8(buffer, scales, count, dim) {
        const view = isBinaryView(buffer, 1)
            ? buffer
            : new Int8Array(buffer.buffer || buffer, buffer.byteOffset || 0, count * dim);

        const rows = [];
        for (let row = 0; row < count; row++) {
            const scale = Number(scales && scales[row]) || 1;
            const values = new Float32Array(dim);
            const base = row * dim;
            for (let column = 0; column < dim; column++) {
                values[column] = view[base + column] * scale;
            }
            rows.push(values);
        }
        return rows;
    }

    /**
     * Dot product of two equal-length vectors.
     *
     * @param {ArrayLike<number>} a
     * @param {ArrayLike<number>} b
     * @returns {number}
     */
    function dot(a, b) {
        let sum = 0;
        for (let index = 0; index < a.length; index++) {
            sum += a[index] * b[index];
        }
        return sum;
    }

    /**
     * Cosine similarity of two vectors.
     *
     * @param {ArrayLike<number>} a
     * @param {ArrayLike<number>} b
     * @returns {number}
     */
    function cosine(a, b) {
        if (!a || !b || !a.length || a.length !== b.length) return 0;
        let normA = 0;
        let normB = 0;
        for (let index = 0; index < a.length; index++) {
            normA += a[index] * a[index];
            normB += b[index] * b[index];
        }
        if (normA === 0 || normB === 0) return 0;
        return dot(a, b) / (Math.sqrt(normA) * Math.sqrt(normB));
    }

    /**
     * Softmax over a list of numbers.
     *
     * @param {number[]} values
     * @param {number} [scale]
     * @returns {number[]}
     */
    function softmax(values, scale) {
        const list = values || [];
        if (list.length === 0) return [];
        const factor = Number.isFinite(scale) ? scale : CONFIG.SCALE;
        const max = list.reduce(function (best, value) { return Math.max(best, value); }, -Infinity);
        const exponentials = list.map(function (value) { return Math.exp(factor * (value - max)); });
        const total = exponentials.reduce(function (sum, value) { return sum + value; }, 0);
        if (total <= 0) return exponentials.map(function () { return 1 / exponentials.length; });
        return exponentials.map(function (value) { return value / total; });
    }

    /**
     * Score an image vector against a vocabulary matrix.
     *
     * The most similar rows form the shortlist; the softmax runs over that
     * shortlist so the returned probabilities are comparable no matter how
     * large the vocabulary is.
     *
     * @param {ArrayLike<number>} vector normalised image embedding
     * @param {ArrayLike<ArrayLike<number>>} matrix row-major embeddings
     * @param {Array<object>} [entries] metadata rows, aligned with the matrix
     * @param {object} [options] { topK, shortlist, scale }
     * @returns {Array<{index:number, similarity:number, probability:number, entry:object|null}>}
     */
    function scoreVocabulary(vector, matrix, entries, options) {
        const settings = Object.assign({}, CONFIG, options || {});
        if (!vector || !matrix || !matrix.length) return [];

        const scored = [];
        for (let index = 0; index < matrix.length; index++) {
            scored.push({ index: index, similarity: cosine(vector, matrix[index]) });
        }
        scored.sort(function (a, b) { return b.similarity - a.similarity; });

        const shortlistSize = Math.min(settings.SHORTLIST, scored.length);
        const shortlist = scored.slice(0, shortlistSize);
        const probabilities = softmax(shortlist.map(function (row) { return row.similarity; }), settings.SCALE);

        return shortlist
            .map(function (row, position) {
                return {
                    index: row.index,
                    similarity: row.similarity,
                    probability: probabilities[position],
                    entry: entries && entries[row.index] ? entries[row.index] : null,
                };
            })
            .slice(0, settings.OPEN_TOP_K);
    }

    /**
     * Decide what to do with tier-1 candidates.
     *
     *  - "matched"   — a clear leader: the user can confirm and add it.
     *  - "candidates"— the shortlist is too weak to pick for the user, but the
     *                  species are worth listing; the user chooses.
     *  - "weak"      — nothing above CANDIDATE_FLOOR: only the extended list
     *                  is offered.
     *  - "none"      — no vocabulary at all.
     *
     * @param {Array<{probability:number}>} candidates
     * @param {object} [config]
     */
    function decideTier1(candidates, config) {
        const options = Object.assign({}, CONFIG, config || {});
        const list = Array.isArray(candidates) ? candidates : [];

        if (list.length === 0) {
            return { status: 'none', rule: 'tier1-none', candidates: [] };
        }

        const best = list[0];
        const second = list[1] || null;
        const margin = best.probability - (second ? second.probability : 0);

        if (best.probability >= options.OPEN_MIN_PROB && margin >= options.OPEN_MARGIN) {
            return {
                status: 'matched',
                rule: 'tier1-match',
                candidates: list,
                confidence: best.probability,
                margin: margin,
            };
        }

        if (best.probability >= options.CANDIDATE_FLOOR) {
            return {
                status: 'candidates',
                rule: 'tier1-weak',
                candidates: list,
                confidence: best.probability,
                margin: margin,
            };
        }

        return {
            status: 'weak',
            rule: 'tier1-below-floor',
            candidates: [],
            confidence: best.probability,
            margin: margin,
        };
    }

    /**
     * Decide what to do with tier-2 (extended list) candidates.
     *
     * The extended list covers far more species, so it demands a higher
     * probability and a bigger margin than tier 1 - and it never accepts on
     * its own: the user always picks the species.
     *
     * @param {Array<{probability:number}>} candidates
     * @param {object} [config]
     */
    function decideTier2(candidates, config) {
        const options = Object.assign({}, CONFIG, config || {});
        const list = Array.isArray(candidates) ? candidates : [];

        if (list.length === 0) {
            return { status: 'none', rule: 'tier2-none', candidates: [] };
        }

        const best = list[0];
        const second = list[1] || null;
        const margin = best.probability - (second ? second.probability : 0);

        if (best.probability >= options.EXTENDED_MIN_PROB && margin >= options.EXTENDED_MARGIN) {
            return {
                status: 'candidates',
                rule: 'tier2-strong',
                candidates: list,
                confidence: best.probability,
                margin: margin,
                requiresConfirmation: true,
            };
        }

        if (best.probability >= options.CANDIDATE_FLOOR) {
            return {
                status: 'candidates',
                rule: 'tier2-weak',
                candidates: list,
                confidence: best.probability,
                margin: margin,
                requiresConfirmation: true,
            };
        }

        return {
            status: 'weak',
            rule: 'tier2-below-floor',
            candidates: [],
            confidence: best.probability,
            margin: margin,
            requiresConfirmation: true,
        };
    }

    /**
     * Merge tier-1 and tier-2 shortlists into one ranking. The probabilities
     * come from separate softmaxes, so the merge uses the cosine similarity:
     * it is the only score that is on the same scale for both tiers. Tier 2 is
     * always shown with the tier-1 candidates, never instead of them.
     *
     * @param {Array<object>} tier1Candidates
     * @param {Array<object>} tier2Candidates
     * @param {number} [topK]
     * @returns {Array<object>}
     */
    function combineCandidates(tier1Candidates, tier2Candidates, topK) {
        const limit = Number.isFinite(topK) ? topK : CONFIG.OPEN_TOP_K;
        const combined = [];

        (Array.isArray(tier1Candidates) ? tier1Candidates : []).forEach(function (candidate) {
            combined.push(Object.assign({}, candidate, { tier: 1 }));
        });
        (Array.isArray(tier2Candidates) ? tier2Candidates : []).forEach(function (candidate) {
            combined.push(Object.assign({}, candidate, { tier: 2 }));
        });

        combined.sort(function (a, b) {
            if (b.similarity !== a.similarity) return b.similarity - a.similarity;
            return String(a.entry && a.entry.qid).localeCompare(String(b.entry && b.entry.qid));
        });

        return combined.slice(0, limit);
    }

    /**
     * Full open-vocabulary decision.
     *
     * @param {object} input
     *        { tier1: candidates, tier2: candidates, outOfScope: candidates }
     * @param {object} [config]
     */
    function decideOpenVocab(input, config) {
        const options = Object.assign({}, CONFIG, config || {});
        const data = input || {};

        const outOfScope = Array.isArray(data.outOfScope) ? data.outOfScope : [];
        const bestOut = outOfScope[0] || null;
        const tier1 = Array.isArray(data.tier1) ? data.tier1 : [];
        const bestTier1 = tier1[0] || null;

        if (bestOut
            && bestOut.probability >= options.OUT_OF_SCOPE_ACCEPT
            && (!bestTier1 || bestOut.probability - bestTier1.probability >= options.OUT_OF_SCOPE_MARGIN)) {
            return {
                status: 'out-of-scope',
                rule: 'out-of-scope',
                label: bestOut.label,
                confidence: bestOut.probability,
                candidates: [],
                canAdd: false,
                canSave: false,
            };
        }

        const tier1Decision = decideTier1(tier1, options);
        if (tier1Decision.status === 'matched') {
            return Object.assign({}, tier1Decision, {
                tier: 1,
                canAdd: true,
                canSave: false,
                offerExtended: false,
            });
        }

        if (Array.isArray(data.tier2) && data.tier2.length > 0) {
            const combined = data.combined && data.combined.length
                ? data.combined
                : combineCandidates(tier1, data.tier2, options.OPEN_TOP_K);
            const tier2Decision = decideTier2(combined, options);
            return Object.assign({}, tier2Decision, {
                tier: 2,
                candidates: combined,
                canAdd: tier2Decision.status === 'candidates',
                canSave: false,
                offerExtended: false,
            });
        }

        return Object.assign({}, tier1Decision, {
            tier: 1,
            canAdd: tier1Decision.status === 'matched',
            canSave: false,
            offerExtended: tier1Decision.status !== 'matched',
        });
    }

    /**
     * The genus of a scientific name ("Canis lupus" -> "canis").
     *
     * @param {string} scientificName
     * @returns {string}
     */
    function genusOf(scientificName) {
        const parts = String(scientificName || '').trim().toLowerCase().split(/\s+/);
        return parts.length > 1 ? parts[0] : '';
    }

    /**
     * Find the library animal a candidate could be added to as a type.
     *
     * A species is only offered as a type of a library animal when their
     * genera match (a red fox "Vulpes vulpes" is a type of the library fox),
     * or when the candidate's common name names the library animal.
     *
     * @param {object} candidate vocabulary entry
     * @param {Array<{common_name:string, scientific_name:string, coco_label:string}>} libraryAnimals
     * @returns {object|null} the matching library animal
     */
    function matchAddAsType(candidate, libraryAnimals) {
        if (!candidate) return null;
        const library = Array.isArray(libraryAnimals) ? libraryAnimals : [];
        if (library.length === 0) return null;

        const candidateGenus = genusOf(candidate.scientific_name);
        const candidateName = String(candidate.common_name || '').toLowerCase().trim();

        for (const animal of library) {
            const animalGenus = genusOf(animal.scientific_name);
            if (candidateGenus && animalGenus && candidateGenus === animalGenus) {
                return animal;
            }
        }

        for (const animal of library) {
            const name = String(animal.common_name || '').toLowerCase().trim();
            if (name && candidateName && (candidateName === name || candidateName.startsWith(name + ' '))) {
                return animal;
            }
        }

        return null;
    }

    /**
     * The name to show for a vocabulary entry. Species without an English
     * common name fall back to the scientific name.
     *
     * @param {object} entry
     * @returns {string}
     */
    function displayName(entry) {
        if (!entry) return '';
        if (entry.name_missing) {
            const group = entry.group_noun || entry.group_label || 'species';
            return entry.scientific_name
                ? `${entry.scientific_name} (${group})`
                : (entry.common_name || 'Unknown species');
        }
        return entry.common_name || entry.scientific_name || 'Unknown species';
    }

    const EcoSightOpenVocab = {
        CONFIG,
        DEFAULTS: CONFIG,
        halfToFloat,
        decodeFloat16,
        decodeInt8,
        dot,
        cosine,
        softmax,
        scoreVocabulary,
        decideTier1,
        decideTier2,
        decideOpenVocab,
        combineCandidates,
        matchAddAsType,
        displayName,
        genusOf,
    };

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = EcoSightOpenVocab;
    }
    global.EcoSightOpenVocab = EcoSightOpenVocab;
})(typeof window !== 'undefined' ? window : globalThis);
