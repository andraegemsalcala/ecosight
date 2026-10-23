/**
 * EcoSight extended-vocabulary cache.
 *
 * The tier-2 vocabulary is a few megabytes, so it is downloaded only when the
 * user asks for the extended list and then kept in IndexedDB. The next scan
 * reuses the cached copy instead of downloading it again.
 */
const DB_NAME = 'ecosight-open-vocab';
const DB_VERSION = 1;
const STORE = 'vocabularies';

function openDatabase() {
    return new Promise((resolve, reject) => {
        if (typeof indexedDB === 'undefined') {
            reject(new Error('IndexedDB is not available in this browser'));
            return;
        }
        const request = indexedDB.open(DB_NAME, DB_VERSION);
        request.onupgradeneeded = () => {
            const db = request.result;
            if (! db.objectStoreNames.contains(STORE)) {
                db.createObjectStore(STORE, { keyPath: 'key' });
            }
        };
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

async function withStore(mode, callback) {
    const db = await openDatabase();
    try {
        return await new Promise((resolve, reject) => {
            const transaction = db.transaction(STORE, mode);
            const store = transaction.objectStore(STORE);
            const request = callback(store);
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    } finally {
        db.close();
    }
}

/**
 * Read a cached vocabulary by key.
 *
 * A cached copy whose checksum no longer matches its metadata is dropped: a
 * half-written or truncated download must never be scored.
 *
 * @param {string} key e.g. "tier2"
 * @returns {Promise<{metadata:object, buffer:ArrayBuffer}|null>}
 */
export async function getCachedVocabulary(key) {
    try {
        const record = await withStore('readonly', (store) => store.get(key));
        if (! record || ! record.buffer) return null;
        if (! await verifyVocabulary(record.metadata, record.buffer)) {
            await clearCachedVocabulary(key);
            return null;
        }
        return { metadata: record.metadata, buffer: record.buffer };
    } catch {
        return null;
    }
}

/**
 * The SHA-256 of a buffer as a lowercase hex string.
 *
 * @param {ArrayBuffer|Uint8Array} buffer
 * @returns {Promise<string|null>} null when the browser has no SubtleCrypto
 */
export async function sha256Hex(buffer) {
    const subtle = globalThis.crypto && globalThis.crypto.subtle;
    if (! subtle) return null;

    const bytes = buffer instanceof Uint8Array ? buffer : new Uint8Array(buffer);
    const digest = await subtle.digest('SHA-256', bytes);

    return Array.from(new Uint8Array(digest))
        .map((byte) => byte.toString(16).padStart(2, '0'))
        .join('');
}

/**
 * Whether a vocabulary buffer matches the checksum in its metadata.
 *
 * Browsers without SubtleCrypto (an insecure context) cannot verify the
 * download, and the check is skipped rather than blocking the scan.
 *
 * @param {object} metadata
 * @param {ArrayBuffer|Uint8Array} buffer
 * @returns {Promise<boolean>}
 */
export async function verifyVocabulary(metadata, buffer) {
    if (! metadata || ! metadata.sha256 || ! buffer) return true;

    const actual = await sha256Hex(buffer);

    return actual ? actual === metadata.sha256 : true;
}

/**
 * Store a vocabulary for later reuse.
 *
 * @param {string} key
 * @param {object} metadata
 * @param {ArrayBuffer} buffer
 */
export async function cacheVocabulary(key, metadata, buffer) {
    try {
        await withStore('readwrite', (store) => store.put({ key, metadata, buffer }));
    } catch {
        // A full or unavailable database must never break the scan.
    }
}

/**
 * Remove a cached vocabulary (used by the "remove download" action).
 *
 * @param {string} key
 */
export async function clearCachedVocabulary(key) {
    try {
        await withStore('readwrite', (store) => store.delete(key));
    } catch {
        // Ignore: the cache is an optimisation.
    }
}

/**
 * Download a vocabulary with progress reporting.
 *
 * @param {string} metadataUrl
 * @param {string} binaryUrl
 * @param {(loaded:number, total:number|null) => void} [onProgress]
 * @returns {Promise<{metadata:object, buffer:ArrayBuffer}>}
 */
export async function downloadVocabulary(metadataUrl, binaryUrl, onProgress) {
    const metadataResponse = await fetch(metadataUrl, { cache: 'no-cache' });
    if (! metadataResponse.ok) {
        throw new Error(`Vocabulary metadata unavailable (${metadataResponse.status})`);
    }
    const metadata = await metadataResponse.json();

    const binaryResponse = await fetch(binaryUrl, { cache: 'no-cache' });
    if (! binaryResponse.ok) {
        throw new Error(`Vocabulary download failed (${binaryResponse.status})`);
    }

    const totalHeader = binaryResponse.headers.get('content-length');
    const total = totalHeader ? Number(totalHeader) : null;

    if (! binaryResponse.body || ! onProgress) {
        const buffer = await binaryResponse.arrayBuffer();
        if (onProgress) onProgress(buffer.byteLength, total);
        if (! await verifyVocabulary(metadata, buffer)) {
            throw new Error('The vocabulary download is damaged and was discarded.');
        }
        return { metadata, buffer };
    }

    const reader = binaryResponse.body.getReader();
    const chunks = [];
    let loaded = 0;
    for (;;) {
        const { done, value } = await reader.read();
        if (done) break;
        chunks.push(value);
        loaded += value.byteLength;
        onProgress(loaded, total);
    }

    const buffer = new Uint8Array(loaded);
    let offset = 0;
    for (const chunk of chunks) {
        buffer.set(chunk, offset);
        offset += chunk.byteLength;
    }

    if (! await verifyVocabulary(metadata, buffer.buffer)) {
        throw new Error('The vocabulary download is damaged and was discarded.');
    }

    return { metadata, buffer: buffer.buffer };
}
