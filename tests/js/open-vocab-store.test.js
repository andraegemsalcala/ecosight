/**
 * Unit tests for public/js/open-vocab-store.js.
 *
 * The store keeps the downloaded species vocabulary in IndexedDB. The download
 * is verified against the checksum in the metadata, so a damaged or truncated
 * file is never scored. These tests cover that verification; the IndexedDB
 * parts need a browser and are exercised by the smoke test.
 */
import test from 'node:test';
import assert from 'node:assert/strict';

import { sha256Hex, verifyVocabulary } from '../../public/js/open-vocab-store.js';

const encoder = new TextEncoder();

test('sha256Hex matches the known digest of "abc"', async () => {
    const digest = await sha256Hex(encoder.encode('abc'));

    assert.equal(digest, 'ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad');
});

test('sha256Hex accepts an ArrayBuffer as well as a view', async () => {
    const bytes = encoder.encode('abc');
    const fromView = await sha256Hex(bytes);
    const fromBuffer = await sha256Hex(bytes.buffer);

    assert.equal(fromView, fromBuffer);
});

test('verifyVocabulary accepts a buffer that matches the checksum', async () => {
    const bytes = encoder.encode('abc');
    const digest = await sha256Hex(bytes);

    assert.equal(await verifyVocabulary({ sha256: digest }, bytes), true);
});

test('verifyVocabulary rejects a buffer that does not match the checksum', async () => {
    const bytes = encoder.encode('abc');
    const digest = await sha256Hex(encoder.encode('a different payload'));

    assert.equal(await verifyVocabulary({ sha256: digest }, bytes), false);
});

test('verifyVocabulary accepts metadata without a checksum', async () => {
    assert.equal(await verifyVocabulary({}, encoder.encode('abc')), true);
});
