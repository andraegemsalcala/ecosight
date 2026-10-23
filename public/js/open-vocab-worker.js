/**
 * EcoSight open-vocabulary worker.
 *
 * Tier 1 is small (a few hundred KB) and is fetched straight from the server.
 * Tier 2 is ~1-10 MB, so the main thread downloads it with a progress bar,
 * keeps it in IndexedDB and hands the buffer to this worker. The heavy matrix
 * then lives off the main thread and the UI stays responsive.
 */
importScripts('/js/open-vocab-core.js');

let tier1 = null;
let tier2 = null;

async function loadJson(url) {
    const response = await fetch(url, { cache: 'force-cache' });
    if (!response.ok) throw new Error(`Failed to load ${url}: ${response.status}`);
    return response.json();
}

async function loadBinary(url) {
    const response = await fetch(url, { cache: 'force-cache' });
    if (!response.ok) throw new Error(`Failed to load ${url}: ${response.status}`);
    return response.arrayBuffer();
}

async function ensureTier1() {
    if (tier1) return tier1;
    const [metadata, binary] = await Promise.all([
        loadJson('/data/animal-vocabulary.json'),
        loadBinary('/data/animal-vocabulary.f16.bin'),
    ]);
    const matrix = self.EcoSightOpenVocab.decodeFloat16(new Uint16Array(binary), metadata.count, metadata.dim);
    tier1 = { metadata, matrix, entries: metadata.entries || [] };
    return tier1;
}

function setTier2(metadata, buffer) {
    if (!metadata || !metadata.count || !buffer || !buffer.byteLength) {
        tier2 = { metadata, matrix: [], entries: [] };
        return tier2;
    }
    const matrix = self.EcoSightOpenVocab.decodeInt8(
        new Int8Array(buffer),
        metadata.scales || [],
        metadata.count,
        metadata.dim
    );
    tier2 = { metadata, matrix, entries: metadata.entries || [] };
    return tier2;
}

self.addEventListener('message', async (event) => {
    const data = event.data || {};
    const id = data.id;

    try {
        if (data.type === 'load-tier1') {
            const loaded = await ensureTier1();
            self.postMessage({ id, type: 'ready', tier: 1, count: loaded.entries.length });
            return;
        }

        if (data.type === 'set-tier2') {
            const loaded = setTier2(data.metadata, data.buffer);
            self.postMessage({ id, type: 'ready', tier: 2, count: loaded.entries.length });
            return;
        }

        if (data.type === 'score') {
            const loaded = await ensureTier1();
            const vector = data.vector;

            const tier1Candidates = self.EcoSightOpenVocab.scoreVocabulary(vector, loaded.matrix, loaded.entries, {
                shortlist: data.shortlist || 20,
                scale: 100,
                OPEN_TOP_K: 5,
            });

            let tier2Candidates = [];
            if (tier2 && tier2.matrix.length) {
                tier2Candidates = self.EcoSightOpenVocab.scoreVocabulary(vector, tier2.matrix, tier2.entries, {
                    shortlist: data.shortlist || 20,
                    scale: 100,
                    OPEN_TOP_K: 5,
                });
            }

            const combinedCandidates = tier2Candidates.length
                ? self.EcoSightOpenVocab.combineCandidates(tier1Candidates, tier2Candidates, 5)
                : tier1Candidates;

            self.postMessage({ id, type: 'result', tier1Candidates, tier2Candidates, combinedCandidates });
            return;
        }
    } catch (error) {
        self.postMessage({ id, type: 'error', message: error.message });
    }
});
