/**
 * Verify the committed open-vocabulary assets:
 *
 *   public/data/animal-vocabulary.json         + animal-vocabulary.f16.bin
 *   public/data/animal-vocabulary-extended.json + animal-vocabulary-extended.i8.bin
 *
 * Checks the metadata against the binary matrix (row count, byte length,
 * SHA-256), the entries themselves (unique identifiers, contiguous row
 * indices, group quota) and that tier 2 never repeats a tier 1 species.
 *
 * Run with: npm run validate:vocabulary
 * Exit code 1 means at least one asset is stale or broken.
 */
import { createHash } from 'node:crypto';
import { existsSync, readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const repoRoot = join(dirname(fileURLToPath(import.meta.url)), '..');
const dataDir = join(repoRoot, 'public', 'data');

const TIERS = [
    {
        label: 'tier 1',
        metadata: 'animal-vocabulary.json',
        binary: 'animal-vocabulary.f16.bin',
        bytesPerValue: 2,
        expectedCount: 3750,
        maxQuota: { mammals: 800, birds: 1300, reptiles: 600, amphibians: 350, insects: 500, arachnids: 200 },
    },
    {
        label: 'tier 2',
        metadata: 'animal-vocabulary-extended.json',
        binary: 'animal-vocabulary-extended.i8.bin',
        bytesPerValue: 1,
        expectedCount: 20000,
        maxQuota: null,
    },
];

let failures = 0;

function check(condition, message) {
    if (condition) {
        console.log(`  OK   ${message}`);
    } else {
        failures += 1;
        console.log(`  FAIL ${message}`);
    }
}

const loaded = {};

for (const tier of TIERS) {
    console.log(`\n${tier.label}: ${tier.metadata}`);

    const metadataPath = join(dataDir, tier.metadata);
    const binaryPath = join(dataDir, tier.binary);

    if (! existsSync(metadataPath) || ! existsSync(binaryPath)) {
        check(false, `assets exist (${tier.metadata} + ${tier.binary})`);
        continue;
    }

    const metadata = JSON.parse(readFileSync(metadataPath, 'utf8'));
    const binary = readFileSync(binaryPath);
    const entries = Array.isArray(metadata.entries) ? metadata.entries : [];
    const dimensions = Number(metadata.dim) || 0;
    const expectedBytes = entries.length * dimensions * tier.bytesPerValue;

    check(metadata.count === entries.length, `count matches the entry list (${entries.length})`);
    check(metadata.dim === 512, `dimension is 512 (${metadata.dim})`);
    check(tier.bytesPerValue === 2 ? metadata.dtype === 'float16' : metadata.dtype === 'int8', `dtype is ${tier.bytesPerValue === 2 ? 'float16' : 'int8'} (${metadata.dtype})`);
    check(binary.length === expectedBytes, `binary is ${binary.length} bytes (expected ${expectedBytes})`);
    check(metadata.bytes === binary.length, 'metadata byte length matches the binary');
    check(
        createHash('sha256').update(binary).digest('hex') === metadata.sha256,
        'SHA-256 checksum matches the metadata',
    );

    if (tier.expectedCount) {
        check(entries.length === tier.expectedCount, `row count is ${tier.expectedCount} (${entries.length})`);
    }

    const unique = new Set(entries.map((entry) => entry.qid));
    check(unique.size === entries.length, `identifiers are unique (${entries.length - unique.size} duplicates)`);
    check(entries.every((entry, index) => entry.row === index), 'row indices are contiguous');

    const byGroup = {};
    for (const entry of entries) {
        byGroup[entry.group] = (byGroup[entry.group] || 0) + 1;
    }
    console.log(`  groups: ${JSON.stringify(byGroup)}`);

    if (tier.maxQuota) {
        for (const [group, quota] of Object.entries(tier.maxQuota)) {
            check((byGroup[group] || 0) <= quota, `${group} stays within ${quota} rows (${byGroup[group] || 0})`);
        }
    }

    loaded[tier.label] = entries;
}

if (loaded['tier 1'] && loaded['tier 2']) {
    const tier1 = new Set(loaded['tier 1'].map((entry) => entry.qid));
    const overlap = loaded['tier 2'].filter((entry) => tier1.has(entry.qid)).length;
    console.log('\ntier overlap');
    check(overlap === 0, `no tier 2 species repeats a tier 1 species (${overlap})`);
}

if (failures > 0) {
    console.log(`\n${failures} check(s) failed.`);
    process.exit(1);
}

console.log('\nVocabulary assets OK.');
