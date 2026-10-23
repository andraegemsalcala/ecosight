/**
 * Generate public/data/imagenet-groups.json from the ImageNet-1000 class list
 * shipped with @tensorflow-models/mobilenet (devDependency).
 *
 * The MobileNet fallback sees a top-1 label such as "golden retriever"; the
 * generated map aggregates those 1000 labels into our scannable animal groups
 * so the decision rules ("accept", "agree", "distractor", "unsure") can work
 * on groups instead of individual breeds. Most-specific records win: explicit
 * single-index mappings (e.g. polar_bear) are applied before broad ranges
 * (bird, dog), so pelican/penguin are not swallowed by the bird ranges.
 *
 * Run with: npm run build:groups
 */
import { createRequire } from 'node:module';
import { mkdirSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const require = createRequire(import.meta.url);
const { IMAGENET_CLASSES } = require('@tensorflow-models/mobilenet/dist/imagenet_classes.js');

const repoRoot = join(dirname(fileURLToPath(import.meta.url)), '..');
const outputPath = join(repoRoot, 'public', 'data', 'imagenet-groups.json');

/** All 33 scannable library animals (coco_label values). */
const KNOWN_ANIMAL_KEYS = [
    'bird', 'cat', 'dog', 'horse', 'sheep', 'cow', 'elephant', 'bear', 'zebra', 'giraffe',
    'tiger', 'common_fox', 'cheetah', 'brown_bear', 'polar_bear', 'wolf', 'red_panda',
    'penguin', 'pelican', 'lion', 'lynx', 'wild_boar', 'koala', 'kangaroo', 'giant_panda',
    'jaguar', 'gorilla', 'otter', 'leopard', 'gazelle',
    'pet_guinea_pig', 'pet_hamster', 'pet_rabbit',
];

/** Specific ImageNet indices -> animal (applied first, wins over ranges). */
const SPECIFIC = {
    wolf: [269, 270],
    common_fox: [277, 278],
    cat: [281, 282, 283, 284, 285],
    lynx: [287],
    leopard: [288],
    jaguar: [290],
    lion: [291],
    tiger: [292],
    cheetah: [293],
    brown_bear: [294],
    bear: [295, 297],
    polar_bear: [296],
    kangaroo: [104],
    koala: [105],
    pelican: [144],
    penguin: [145],
    pet_rabbit: [332],
    pet_hamster: [333],
    pet_guinea_pig: [338],
    horse: [339],
    zebra: [340],
    wild_boar: [342],
    cow: [345],
    sheep: [348],
    gazelle: [353],
    otter: [360],
    gorilla: [366],
    elephant: [385, 386],
    red_panda: [387],
    giant_panda: [388],
};

/** Broad inclusive index ranges -> animal (fill only unassigned indices). */
const RANGES = {
    bird: [[7, 24], [80, 100], [127, 146]],
    dog: [[151, 268]],
};

const labels = Object.values(IMAGENET_CLASSES);
if (labels.length !== 1000) {
    throw new Error(`Expected 1000 ImageNet classes, found ${labels.length}`);
}

const assigned = new Map();
const conflicts = [];

for (const [animal, indices] of Object.entries(SPECIFIC)) {
    if (!KNOWN_ANIMAL_KEYS.includes(animal)) {
        throw new Error(`Unknown animal key in SPECIFIC: ${animal}`);
    }
    for (const index of indices) {
        if (assigned.has(index)) {
            throw new Error(`Index ${index} (${labels[index]}) assigned twice: ${assigned.get(index)} and ${animal}`);
        }
        assigned.set(index, animal);
    }
}

for (const [animal, ranges] of Object.entries(RANGES)) {
    if (!KNOWN_ANIMAL_KEYS.includes(animal)) {
        throw new Error(`Unknown animal key in RANGES: ${animal}`);
    }
    for (const [start, end] of ranges) {
        for (let index = start; index <= end; index++) {
            if (assigned.has(index)) {
                conflicts.push(`${index} (${labels[index]}): kept ${assigned.get(index)}, skipped generic ${animal}`);
                continue;
            }
            assigned.set(index, animal);
        }
    }
}

const groupLabels = {};
const labelToAnimal = {};
for (const [index, animal] of [...assigned.entries()].sort((a, b) => a[0] - b[0])) {
    const label = labels[index];
    if (labelToAnimal[label] && labelToAnimal[label] !== animal) {
        throw new Error(`Label "${label}" maps to both ${labelToAnimal[label]} and ${animal}`);
    }
    labelToAnimal[label] = animal;
    (groupLabels[animal] ??= []).push(label);
}

const unmappedAnimals = KNOWN_ANIMAL_KEYS.filter((animal) => !groupLabels[animal]);
const sortedGroups = Object.fromEntries(Object.keys(groupLabels).sort().map((key) => [key, groupLabels[key]]));
const sortedLabels = Object.fromEntries(Object.keys(labelToAnimal).sort().map((key) => [key, labelToAnimal[key]]));

const output = {
    version: 1,
    source: '@tensorflow-models/mobilenet@2.1.1 ImageNet-1000 class list',
    labelCount: labels.length,
    mappedLabelCount: Object.keys(sortedLabels).length,
    labels: sortedLabels,
    groups: sortedGroups,
};

mkdirSync(dirname(outputPath), { recursive: true });
writeFileSync(outputPath, `${JSON.stringify(output, null, 2)}\n`);

for (const [animal, groupList] of Object.entries(sortedGroups)) {
    console.log(`\n${animal} (${groupList.length})`);
    for (const label of groupList) {
        console.log(`  ${label}`);
    }
}

if (conflicts.length > 0) {
    console.log(`\nGeneric ranges skipped ${conflicts.length} specifically-mapped index(es):`);
    for (const conflict of conflicts) {
        console.log(`  ${conflict}`);
    }
}

console.log(`\nMapped ${output.mappedLabelCount}/1000 labels into ${Object.keys(sortedGroups).length} groups.`);
if (unmappedAnimals.length > 0) {
    console.log(`Library animals with no ImageNet mapping: ${unmappedAnimals.join(', ')}`);
}
console.log(`Wrote ${outputPath}`);
