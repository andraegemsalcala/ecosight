/**
 * Build the open-vocabulary assets from Wikidata.
 *
 * Tier 1 is a compact Float16 matrix used on every scan. Tier 2 is an optional
 * int8 matrix downloaded only when the user asks for the extended list.
 *
 * Run:
 *   node scripts/build-animal-vocabulary.mjs --tier=1 --limit=50
 *   node scripts/build-animal-vocabulary.mjs --tier=1
 *   node scripts/build-animal-vocabulary.mjs --tier=2 --resume
 *   node scripts/build-animal-vocabulary.mjs --group=mammals --limit=200
 *
 * The script uses Node fetch (not curl) because some networks block curl
 * requests to Wikidata. Raw responses are cached under storage/app/vocab-cache
 * and the build is resumable. Attribution: Wikidata data is CC0; Wikipedia text
 * used by the scanner is CC BY-SA 4.0.
 *
 * Collection runs in two stages because the obvious single query is far too
 * slow on the public endpoint:
 *
 *   Stage A  fetches a pool of species QIDs per group, ordered by how many
 *            Wikipedia articles the taxon has. That ranking is the cheapest
 *            available proxy for "an ordinary person could recognise this",
 *            and it is what the tier 1 and tier 2 ordering is built on.
 *   Stage B  resolves the labels, scientific names, taxonomy and conservation
 *            status for that pool in small VALUES batches, which the endpoint
 *            answers quickly.
 *
 * Stage A for insects is split per order: the whole-group query times out.
 * The per-anchor queries are answered in a couple of seconds each.
 */
import { createHash } from 'node:crypto';
import { existsSync, mkdirSync, readFileSync, statSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { ensureClipModel, importTransformers, useLocalModels } from './ensure-clip-tool.mjs';

const CLIP_MODEL_ID = 'Xenova/clip-vit-base-patch32';
const DTYPE = 'q8';
const EMBEDDING_DIM = 512;
const EMBED_BATCH_SIZE = 48;
const OUTPUT_VERSION = 1;

const CACHE_VERSION = 'v2';
const SPARQL_ENDPOINT = 'https://query.wikidata.org/sparql';
const SPARQL_TIMEOUT_MS = 180000;
const SPARQL_RETRIES = 4;
const METADATA_BATCH_SIZE = 200;
const INSECT_ANCHOR_LIMIT = 250;
const ARACHNID_ANCHOR_LIMIT = 400;

const repoRoot = join(dirname(fileURLToPath(import.meta.url)), '..');
const cacheDir = join(repoRoot, 'storage', 'app', 'vocab-cache');
const publicDataDir = join(repoRoot, 'public', 'data');
const userAgent = process.env.WIKIMEDIA_USER_AGENT || 'EcoSight/1.0 (student project; +http://ecosight.test)';

const GROUP_DEFINITIONS = {
    mammals: { qid: 'Q7377', label: 'Mammals', noun: 'Mammal', quota: 800 },
    birds: { qid: 'Q5113', label: 'Birds', noun: 'Bird', quota: 1300 },
    reptiles: { qid: 'Q10811', label: 'Reptiles', noun: 'Reptile', quota: 600 },
    amphibians: { qid: 'Q10908', label: 'Amphibians', noun: 'Amphibian', quota: 350 },
    insects: { qid: 'Q1390', label: 'Insects', noun: 'Insect', quota: 500 },
    arachnids: { qid: 'Q1358', label: 'Arachnids', noun: 'Spider or arachnid', quota: 200 },
};

const EXTENDED_MAX = 20000;
const TIER1_TEMPLATES = [
    'a photo of a {name}',
    'a photo of a {name} in the wild',
    'a photo of a {name} in a zoo',
    'a close-up photo of a {name}',
];
const TIER2_TEMPLATES = [
    'a photo of a {name}',
    'a photo of {scientific}, a {group_noun}',
];

/**
 * Marine lineages that are not part of the scanner vocabulary. Every marine
 * root is expanded to all of its descendants so that a species is recognised
 * as marine through its family or genus as well as directly.
 */
const MARINE_ROOTS = [
    'Q160', 'Q25431', 'Q219329', 'Q460286', 'Q11753180', 'Q25587', 'Q26700', 'Q2292156',
];

/**
 * Families that are excluded outright even where the marine closure does not
 * reach them.
 */
const EXCLUDED_FAMILIES = new Set([
    'Q782603', 'Q232829', 'Q898360', 'Q9049302', 'Q1574757', 'Q1965630', 'Q193106',
    'Q215760', 'Q219419', 'Q10867', 'Q54873', 'Q6078080', 'Q13410384', 'Q3242241',
    'Q10827895', 'Q838603', 'Q26700', 'Q2292156', 'Q25587', 'Q30143', 'Q2738058',
]);

/**
 * Marine taxa that sit outside the marine roots. The sea otter belongs to the
 * weasel family, so it is not caught by the cetacean, seal or manatee roots.
 */
const EXCLUDED_TAXA = new Set([
    'Q41407', 'Q5832825',
]);

/**
 * Wikidata "instance of" classes that mean the row is not a usable species
 * name: a fossil, a synonym, an obsolete combination and so on. Extinct but
 * real species (the dodo, the thylacine) stay in the vocabulary because people
 * still photograph them.
 */
const REJECTED_INSTANCE_OF = new Set([
    'Q23038290', 'Q47487597', 'Q1040689', 'Q42310380', 'Q763978', 'Q922448',
    'Q14594740', 'Q17487588', 'Q1984758', 'Q110533142', 'Q124477390', 'Q2568288',
    'Q136343650', 'Q4915935',
]);

const IUCN_STATUSES = {
    Q219127: 'Critically Endangered',
    Q11394: 'Endangered',
    Q96377276: 'Endangered',
    Q278113: 'Vulnerable',
    Q719675: 'Near Threatened',
    Q211005: 'Least Concern',
    Q3245245: 'Data Deficient',
    Q239509: 'Extinct in the Wild',
    Q237350: 'Extinct',
};

/**
 * Fallback anchors for the insect stage A sweep. The live discovery query
 * occasionally times out on the public endpoint, so the script keeps a known
 * good list of insect orders and falls back to it.
 */
const INSECT_FALLBACK_ANCHORS = [
    'Q188029', 'Q194025', 'Q388162', 'Q174273', 'Q185628', 'Q203547', 'Q271623',
    'Q131250', 'Q1185781', 'Q136109', 'Q226951', 'Q156438', 'Q164689', 'Q167810',
    'Q205301', 'Q1949507', 'Q1952081', 'Q217516', 'Q13167534', 'Q327144', 'Q2839447',
    'Q3409994', 'Q467392', 'Q4040884', 'Q4135955', 'Q540502', 'Q4344585', 'Q4458210',
    'Q19372238', 'Q19509894', 'Q19652150', 'Q19655097', 'Q637179', 'Q21012945',
    'Q21446253', 'Q754029', 'Q796470', 'Q26433983', 'Q26713533', 'Q26787975',
    'Q27096119', 'Q28757963', 'Q8255832', 'Q963767', 'Q999198', 'Q13676', 'Q22651',
    'Q25309', 'Q25312', 'Q26371', 'Q28319', 'Q35133346', 'Q35133761', 'Q22671',
    'Q25375', 'Q49381348', 'Q56479261', 'Q54763173', 'Q54824881', 'Q56071470',
    'Q30111', 'Q111721085', 'Q184616', 'Q16080917', 'Q4045034', 'Q4893793',
    'Q20857155', 'Q21346589', 'Q33120782', 'Q35133436', 'Q120278768', 'Q1073158',
    'Q3037286', 'Q4047470', 'Q45353969',
];

/**
 * The arachnid orders, used the same way as the insect anchors.
 */
const ARACHNID_ANCHORS = [
    'Q1357', 'Q19125', 'Q19119', 'Q19126', 'Q137929', 'Q686325', 'Q10304508',
    'Q1139948', 'Q1240844', 'Q1387882', 'Q30587689', 'Q14849555', 'Q3111386',
    'Q3552134', 'Q19962629',
];

const args = process.argv.slice(2);
function argValue(name, fallback = null) {
    const prefix = `--${name}=`;
    const match = args.find((value) => value.startsWith(prefix));
    return match ? match.slice(prefix.length) : fallback;
}
const tierArg = argValue('tier', 'both');
const groupArg = argValue('group', null);
const limitArg = Number.parseInt(argValue('limit', '0'), 10);
const groupLimit = Number.isFinite(limitArg) && limitArg > 0 ? limitArg : 0;
const resume = args.includes('--resume');
const refresh = args.includes('--refresh');

if (! ['1', '2', 'both'].includes(tierArg)) {
    throw new Error('--tier must be 1, 2, or both');
}
if (resume) {
    console.log('Resuming from the cached pools in storage/app/vocab-cache.');
}
const selectedGroups = groupArg
    ? groupArg.split(',').map((value) => value.trim()).filter(Boolean)
    : Object.keys(GROUP_DEFINITIONS);
for (const group of selectedGroups) {
    if (! GROUP_DEFINITIONS[group]) {
        throw new Error(`Unknown group: ${group}`);
    }
}

mkdirSync(cacheDir, { recursive: true });
mkdirSync(publicDataDir, { recursive: true });

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

function slugify(value) {
    return String(value)
        .normalize('NFKD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 120);
}

/**
 * The English Wikipedia article title is a good common name when the
 * Wikidata label is the scientific name, which is the case for a fair number
 * of well known species (the polar bear is labelled "Ursus maritimus" in
 * Wikidata while its article is called "Polar bear").
 */
function articleTitle(url) {
    if (! url) {
        return '';
    }
    const withoutFragment = url.split('#')[0];
    const segment = withoutFragment.substring(withoutFragment.lastIndexOf('/') + 1);
    let title = '';
    try {
        title = decodeURIComponent(segment);
    } catch (error) {
        title = segment;
    }
    title = title.replace(/_/g, ' ').trim();
    title = title.replace(/\s*\([^()]*\)\s*$/, '').trim();
    return title;
}

function singularize(word) {
    const lower = word.toLowerCase();

    if (lower.endsWith('ies') && lower.length > 3) {
        return `${word.slice(0, -3)}y`;
    }
    if (lower.endsWith('sses') || lower.endsWith('shes') || lower.endsWith('ches') || lower.endsWith('xes')) {
        return word.slice(0, -2);
    }
    if (lower.endsWith('s') && ! lower.endsWith('ss') && lower.length > 2) {
        return word.slice(0, -1);
    }

    return word;
}

/**
 * The review form calls the broad kind of animal the "general name". For a
 * red fox that is "Fox"; for a European robin it is "Robin". Falls back to the
 * group noun when the common name is missing or is just the scientific name.
 */
function generalGroupName(entry, commonName) {
    const noun = (entry.group_noun || entry.group_label || 'animal').replace(/^(spider or )/i, '');
    if (! commonName || entry.name_missing) {
        return noun.charAt(0).toUpperCase() + noun.slice(1);
    }

    const cleaned = commonName.replace(/\([^)]*\)/g, ' ').trim().split(/\s+/).pop() || noun;
    const singular = singularize(cleaned);

    return singular.charAt(0).toUpperCase() + singular.slice(1);
}

function withCommonName(entry) {
    const scientific = (entry.scientific || '').trim();
    const label = (entry.itemLabel || '').trim();
    const title = articleTitle(entry.wikipedia_url);
    const sameAsScientific = (value) => value.toLowerCase() === scientific.toLowerCase();

    let commonName = '';
    if (label && ! sameAsScientific(label)) {
        commonName = label;
    } else if (title && ! sameAsScientific(title)) {
        commonName = title;
    } else {
        commonName = scientific;
    }

    const genusName = (entry.parent_name || '').trim()
        || scientific.split(' ')[0];

    const nameMissing = ! commonName || sameAsScientific(commonName);

    return {
        ...entry,
        common_name: commonName || scientific,
        group_name: generalGroupName(entry, nameMissing ? '' : commonName),
        name_missing: nameMissing,
        parent_name: genusName,
        scientific_slug: slugify(scientific),
    };
}

async function sparql(query, label) {
    let lastError = null;
    for (let attempt = 1; attempt <= SPARQL_RETRIES; attempt++) {
        try {
            const response = await fetch(SPARQL_ENDPOINT, {
                method: 'POST',
                headers: {
                    'User-Agent': userAgent,
                    Accept: 'application/sparql-results+json',
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `query=${encodeURIComponent(query)}`,
                signal: AbortSignal.timeout(SPARQL_TIMEOUT_MS),
            });
            if ([429, 500, 502, 503, 504].includes(response.status)) {
                throw new Error(`HTTP ${response.status}`);
            }
            if (! response.ok) {
                throw new Error(`HTTP ${response.status} ${response.statusText}`);
            }
            const body = await response.text();
            const parsed = JSON.parse(body);
            return parsed.results?.bindings || [];
        } catch (error) {
            lastError = error;
            if (attempt === SPARQL_RETRIES) {
                break;
            }
            const wait = Math.min(45000, 3000 * 2 ** (attempt - 1));
            console.warn(`  ${label}: ${error.message}, retrying in ${wait} ms`);
            await sleep(wait);
        }
    }
    throw new Error(`SPARQL ${label} failed: ${lastError?.message}`);
}

function cacheFile(name) {
    return join(cacheDir, `${CACHE_VERSION}-${name}`);
}

function readCache(name, fallback = null) {
    const path = cacheFile(name);
    if (refresh || ! existsSync(path)) {
        return fallback;
    }
    try {
        return JSON.parse(readFileSync(path, 'utf8'));
    } catch (error) {
        console.warn(`  ignoring unreadable cache ${name}: ${error.message}`);
        return fallback;
    }
}

function writeCache(name, value) {
    writeFileSync(cacheFile(name), JSON.stringify(value));
}

/* ------------------------------------------------------------------ *
 * Stage A: species pools
 * ------------------------------------------------------------------ */

/**
 * Pool size per group. The tier 1 quota plus an even share of the tier 2
 * budget, with headroom for rows that are dropped later.
 */
function poolTarget(group) {
    if (groupLimit) {
        return groupLimit;
    }
    const share = Math.ceil(EXTENDED_MAX / Object.keys(GROUP_DEFINITIONS).length);
    return GROUP_DEFINITIONS[group].quota + share + 400;
}

/** How many species a group contributes to tier 1. */
function tier1Quota(group) {
    if (groupLimit) {
        return Math.min(GROUP_DEFINITIONS[group].quota, groupLimit);
    }
    return GROUP_DEFINITIONS[group].quota;
}

/** How many species a group contributes to tier 2. */
function tier2Quota(group) {
    if (groupLimit) {
        return groupLimit;
    }
    return Math.ceil(EXTENDED_MAX / Object.keys(GROUP_DEFINITIONS).length);
}

function poolQuery(rootQid, limit, offset) {
    return `
SELECT ?item ?links WHERE {
  ?item wdt:P105 wd:Q7432 ;
        wdt:P171+ wd:${rootQid} ;
        wdt:P225 ?scientific ;
        wikibase:sitelinks ?links .
}
ORDER BY DESC(?links)
LIMIT ${limit} OFFSET ${offset}`;
}

function anchorQuery(anchorQid, limit) {
    return `
SELECT ?item ?links WHERE {
  ?item wdt:P105 wd:Q7432 ;
        wdt:P171+ wd:${anchorQid} ;
        wdt:P225 ?scientific ;
        wikibase:sitelinks ?links .
  FILTER(?links > 0)
}
ORDER BY DESC(?links)
LIMIT ${limit}`;
}

function poolRowsFromBindings(bindings) {
    return bindings.map((row) => ({
        qid: row.item.value.split('/').pop(),
        links: Number.parseInt(row.links.value, 10) || 0,
    }));
}

/**
 * Collect a group pool with a single paged query. Used for every group whose
 * taxonomy subtree the endpoint can walk in one go.
 *
 * `accept` filters rows while the pool is being filled, so that a group whose
 * pool is polluted by a neighbouring group (birds inside the reptile subtree)
 * still ends up with the requested number of usable species.
 */
async function collectGroupPool(group, accept = () => true) {
    const target = poolTarget(group);
    const cacheName = `pool-${group}-${target}-p3.json`;
    const cached = readCache(cacheName);
    if (cached && cached.length >= target) {
        console.log(`${group}: ${cached.length} pooled species from cache`);
        return cached.slice(0, target);
    }

    const definition = GROUP_DEFINITIONS[group];
    const pageSize = 3000;
    const rows = [];
    const seen = new Set();
    let offset = 0;

    while (rows.length < target && offset < 60000) {
        const bindings = await sparql(poolQuery(definition.qid, pageSize, offset), `${group} pool @${offset}`);
        const page = poolRowsFromBindings(bindings);
        if (! page.length) {
            break;
        }
        for (const row of page) {
            if (seen.has(row.qid) || ! accept(row)) {
                continue;
            }
            seen.add(row.qid);
            rows.push(row);
        }
        console.log(`  ${group}: ${rows.length}/${target} (offset ${offset})`);
        if (page.length < pageSize) {
            break;
        }
        offset += pageSize;
        await sleep(400);
    }

    writeCache(cacheName, rows.slice(0, target));
    console.log(`${group}: ${Math.min(rows.length, target)} pooled species`);
    return rows.slice(0, target);
}

/**
 * Collect a pool one order at a time. The whole-group query exceeds the
 * endpoint's time budget for the insect and arachnid subtrees, while the
 * per-order queries are answered in a couple of seconds each.
 */
async function collectAnchorPool(group, anchors, anchorLimit, accept = () => true) {
    const target = poolTarget(group);
    const cacheName = `pool-${group}-${target}-p3.json`;
    const cached = readCache(cacheName);
    if (cached && cached.length >= target) {
        console.log(`${group}: ${cached.length} pooled species from cache`);
        return cached.slice(0, target);
    }

    const rows = [];
    const seen = new Set();

    for (const anchor of anchors) {
        let bindings = [];
        try {
            bindings = await sparql(anchorQuery(anchor, anchorLimit), `${group} ${anchor}`);
        } catch (error) {
            console.warn(`  ${group} ${anchor} failed at ${anchorLimit}: ${error.message}`);
            try {
                bindings = await sparql(anchorQuery(anchor, anchorLimit * 2), `${group} ${anchor} heavy`);
            } catch (heavyError) {
                console.warn(`  ${group} ${anchor} skipped: ${heavyError.message}`);
                await sleep(2000);
                continue;
            }
        }
        for (const row of poolRowsFromBindings(bindings)) {
            if (! seen.has(row.qid) && accept(row)) {
                seen.add(row.qid);
                rows.push(row);
            }
        }
        await sleep(350);
    }

    rows.sort((a, b) => b.links - a.links);
    let result = rows.slice(0, target);

    if (result.length < target) {
        console.log(`  ${group}: topping up ${result.length}/${target} from the group query`);
        const topUp = await collectGroupPool(group, (row) => accept(row) && ! seen.has(row.qid));
        const merged = new Map(result.map((row) => [row.qid, row]));
        for (const row of topUp) {
            if (! merged.has(row.qid)) {
                merged.set(row.qid, row);
            }
        }
        result = [...merged.values()].sort((a, b) => b.links - a.links).slice(0, target);
    }

    writeCache(cacheName, result);
    console.log(`${group}: ${result.length} pooled species`);
    return result;
}

/**
 * The reptile subtree contains every bird, because birds descend from
 * reptiles in the Wikidata taxonomy. The scanner treats them as separate
 * groups, so the bird species are removed from the reptile pool here.
 */
async function birdSpeciesSet() {
    const cached = readCache('bird-species.json');
    if (cached && cached.length) {
        console.log(`birds: ${cached.length} species in exclusion set`);
        return new Set(cached);
    }
    const bindings = await sparql(`
SELECT DISTINCT ?item WHERE {
  ?item wdt:P105 wd:Q7432 ;
        wdt:P171+ wd:Q5113 .
}
LIMIT 40000`, 'bird species');
    const species = bindings.map((row) => row.item.value.split('/').pop());
    console.log(`birds: ${species.length} species in exclusion set`);
    writeCache('bird-species.json', species);
    return new Set(species);
}

/**
 * Expand the marine roots to every descendant taxon so that a species can be
 * recognised as marine through its family or its genus.
 */
async function marineClosure() {
    const cached = readCache('marine-closure.json');
    if (cached && cached.length) {
        console.log(`marine: ${cached.length} taxa in closure`);
        return new Set(cached);
    }
    const closure = new Set(MARINE_ROOTS);
    for (const root of MARINE_ROOTS) {
        const bindings = await sparql(`
SELECT DISTINCT ?taxon WHERE {
  ?taxon wdt:P171+ wd:${root} .
}
LIMIT 20000`, `marine ${root}`);
        for (const row of bindings) {
            closure.add(row.taxon.value.split('/').pop());
        }
        await sleep(250);
    }
    console.log(`marine: ${closure.size} taxa in closure`);
    writeCache('marine-closure.json', [...closure]);
    return closure;
}

/* ------------------------------------------------------------------ *
 * Stage B: metadata
 * ------------------------------------------------------------------ */

function metadataQuery(qids) {
    return `
SELECT ?item ?itemLabel ?scientific ?genus ?genusLabel ?family ?iucn ?article ?instanceOf WHERE {
  VALUES ?item { ${qids.map((qid) => `wd:${qid}`).join(' ')} }
  ?item wdt:P225 ?scientific .
  OPTIONAL { ?item rdfs:label ?itemLabel . FILTER(lang(?itemLabel) = "en") }
  OPTIONAL { ?item wdt:P171 ?genus . OPTIONAL { ?genus rdfs:label ?genusLabel . FILTER(lang(?genusLabel) = "en") } }
  OPTIONAL { ?genus wdt:P171 ?family . }
  OPTIONAL { ?item wdt:P141 ?iucn . }
  OPTIONAL { ?article schema:about ?item ; schema:isPartOf <https://en.wikipedia.org/> . }
  OPTIONAL { ?item wdt:P31 ?instanceOf . }
}`;
}

/**
 * The endpoint returns one row per combination of the optional properties, so
 * the rows for a species are merged into a single record.
 */
function mergeBindings(bindings) {
    const merged = new Map();
    for (const row of bindings) {
        const qid = row.item.value.split('/').pop();
        const record = merged.get(qid) || { qid, instanceOfs: [] };
        const take = (key, value) => {
            if (value !== undefined && value !== null && (record[key] === undefined || record[key] === null)) {
                record[key] = value;
            }
        };
        take('scientific', row.scientific?.value);
        take('itemLabel', row.itemLabel?.value);
        take('genusQid', row.genus?.value ? row.genus.value.split('/').pop() : null);
        take('genusLabel', row.genusLabel?.value);
        take('familyQid', row.family?.value ? row.family.value.split('/').pop() : null);
        take('iucnQid', row.iucn?.value ? row.iucn.value.split('/').pop() : null);
        take('article', row.article?.value);
        merged.set(qid, record);
        const instanceOf = row.instanceOf?.value ? row.instanceOf.value.split('/').pop() : null;
        if (instanceOf && ! record.instanceOfs.includes(instanceOf)) {
            record.instanceOfs.push(instanceOf);
        }
    }
    return merged;
}

/**
 * Resolve the metadata for a pool of species. Batches are cached individually
 * so an interrupted run picks up where it stopped.
 */
async function collectMetadata(group, pool) {
    const cacheName = `meta-${group}.json`;
    const store = readCache(cacheName, {}) || {};
    const pending = pool.filter((row) => ! store[row.qid]);

    if (pending.length) {
        console.log(`${group}: resolving metadata for ${pending.length} species`);
        for (let start = 0; start < pending.length; start += METADATA_BATCH_SIZE) {
            const slice = pending.slice(start, start + METADATA_BATCH_SIZE);
            const bindings = await sparql(metadataQuery(slice.map((row) => row.qid)), `${group} metadata @${start}`);
            const merged = mergeBindings(bindings);
            for (const [qid, record] of merged) {
                store[qid] = record;
            }
            writeCache(cacheName, store);
            console.log(`  ${group}: ${Math.min(start + slice.length, pending.length)}/${pending.length}`);
            await sleep(200);
        }
    }

    return pool.map((row) => {
        const raw = store[row.qid];
        if (! raw) {
            return null;
        }
        return {
            qid: row.qid,
            links: row.links,
            scientific: raw.scientific || '',
            itemLabel: raw.itemLabel || '',
            parent_qid: raw.genusQid || null,
            parent_name: raw.genusLabel || '',
            family_qid: raw.familyQid || null,
            iucn_qid: raw.iucnQid || null,
            wikipedia_url: raw.article || null,
            instance_of: raw.instanceOfs || [],
        };
    }).filter(Boolean);
}

/* ------------------------------------------------------------------ *
 * Filtering and selection
 * ------------------------------------------------------------------ */

function isRejected(row) {
    if (! row.scientific) {
        return true;
    }
    if ((row.instance_of || []).some((qid) => REJECTED_INSTANCE_OF.has(qid))) {
        return true;
    }
    if (row.family_qid && EXCLUDED_FAMILIES.has(row.family_qid)) {
        return true;
    }
    if (row.qid && EXCLUDED_TAXA.has(row.qid)) {
        return true;
    }
    if (row.parent_qid && EXCLUDED_TAXA.has(row.parent_qid)) {
        return true;
    }
    return false;
}

function isMarine(row, closure) {
    return closure.has(row.qid)
        || (row.parent_qid ? closure.has(row.parent_qid) : false)
        || (row.family_qid ? closure.has(row.family_qid) : false);
}

function buildRows(group, metadata, options) {
    const definition = GROUP_DEFINITIONS[group];
    const rows = [];

    for (const row of metadata) {
        if (isRejected(row)) {
            continue;
        }
        if (isMarine(row, options.marine)) {
            continue;
        }
        if (group === 'reptiles' && options.birds.has(row.qid)) {
            continue;
        }
        const iucnQid = row.iucn_qid;
        rows.push(withCommonName({
            ...row,
            iucn_status: iucnQid ? (IUCN_STATUSES[iucnQid] || 'Check IUCN Red List') : 'Check IUCN Red List',
            group,
            group_label: definition.label,
            group_noun: definition.noun,
        }));
    }

    return rows;
}

/**
 * Tier 1 holds the species a person is most likely to photograph. The
 * Wikipedia article count leads the ranking, because it is the same signal
 * that decided the pool order, and the conservation status and the English
 * name break ties.
 */
function rankForTier1(row) {
    return (row.links || 0) * 10
        + (row.wikipedia_url ? 6 : 0)
        + (row.itemLabel ? 4 : 0)
        + (row.iucn_qid ? 2 : 0)
        + (row.parent_name ? 1 : 0);
}

function selectTier1(rowsByGroup) {
    const selected = [];
    for (const group of selectedGroups) {
        const rows = [...(rowsByGroup[group] || [])]
            .filter((row) => row.wikipedia_url)
            .sort((a, b) => {
                const score = rankForTier1(b) - rankForTier1(a);
                return score !== 0 ? score : a.qid.localeCompare(b.qid);
            });
        const quota = tier1Quota(group);
        selected.push(...rows.slice(0, quota));
    }
    return selected;
}

const THREATENED_PRIORITY = {
    'Critically Endangered': 4,
    'Endangered': 3,
    'Vulnerable': 2,
    'Near Threatened': 1,
};

/**
 * The extended list is for the species a learner is least likely to find in a
 * field guide, so threatened species come first and Wikipedia notability
 * (sitelinks) breaks the ties inside each status.
 */
function rankForTier2(row) {
    return (THREATENED_PRIORITY[row.iucn_status] || 0) * 1_000_000 + (row.links || 0);
}

function selectTier2(rowsByGroup, tier1Qids) {
    const pools = {};
    const taken = {};
    for (const group of selectedGroups) {
        pools[group] = [...(rowsByGroup[group] || [])]
            .filter((row) => ! tier1Qids.has(row.qid))
            .sort((a, b) => rankForTier2(b) - rankForTier2(a) || a.qid.localeCompare(b.qid));
        taken[group] = 0;
    }

    const max = groupLimit ? selectedGroups.length * groupLimit : EXTENDED_MAX;
    const selected = [];

    while (selected.length < max) {
        let added = false;
        for (const group of selectedGroups) {
            if (taken[group] >= tier2Quota(group)) {
                continue;
            }
            const next = pools[group].shift();
            if (! next) {
                continue;
            }
            selected.push(next);
            taken[group] += 1;
            added = true;
            if (selected.length >= max) {
                break;
            }
        }
        if (! added) {
            break;
        }
    }
    return selected;
}

/* ------------------------------------------------------------------ *
 * Embedding and output
 * ------------------------------------------------------------------ */

function promptsFor(row, tier) {
    const templates = tier === 1 ? TIER1_TEMPLATES : TIER2_TEMPLATES;
    return templates.map((template) => template
        .replaceAll('{name}', row.common_name)
        .replaceAll('{scientific}', row.scientific)
        .replaceAll('{group_noun}', row.group_noun));
}

function normalize(values) {
    let sum = 0;
    for (const value of values) sum += value * value;
    const norm = Math.sqrt(sum) || 1;
    return values.map((value) => value / norm);
}

function floatToHalf(value) {
    const floatView = new Float32Array(1);
    const intView = new Int32Array(floatView.buffer);
    floatView[0] = value;
    const x = intView[0];

    const sign = (x >> 16) & 0x8000;
    let exponent = ((x >> 23) & 0xff) - 127 + 15;
    let mantissa = x & 0x7fffff;

    if (exponent <= 0) {
        if (exponent < -10) {
            return sign;
        }
        mantissa = (mantissa | 0x800000) >> (1 - exponent);
        return sign | ((mantissa + 0x1000) >> 13);
    }
    if (exponent >= 31) {
        return sign | 0x7c00 | (mantissa ? 0x0200 : 0);
    }
    return sign | (exponent << 10) | ((mantissa + 0x1000) >> 13);
}

function encodeFloat16(vectors) {
    const buffer = Buffer.alloc(vectors.length * EMBEDDING_DIM * 2);
    let offset = 0;
    for (const vector of vectors) {
        for (const value of vector) {
            buffer.writeUInt16LE(floatToHalf(value), offset);
            offset += 2;
        }
    }
    return buffer;
}

function encodeInt8(vectors) {
    const buffer = Buffer.alloc(vectors.length * EMBEDDING_DIM);
    const scales = [];
    let offset = 0;
    for (const vector of vectors) {
        let max = 0;
        for (const value of vector) max = Math.max(max, Math.abs(value));
        const scale = max > 0 ? max / 127 : 1;
        scales.push(scale);
        for (const value of vector) {
            const quantized = Math.max(-127, Math.min(127, Math.round(value / scale)));
            buffer.writeInt8(quantized, offset);
            offset += 1;
        }
    }
    return { buffer, scales };
}

function sha256(buffer) {
    return createHash('sha256').update(buffer).digest('hex');
}

async function embedRows(rows, tier) {
    const { root } = await ensureClipModel();
    const transformers = await importTransformers();
    useLocalModels(transformers, root);

    const { AutoTokenizer, CLIPTextModelWithProjection } = transformers;
    const tokenizer = await AutoTokenizer.from_pretrained(CLIP_MODEL_ID);
    const model = await CLIPTextModelWithProjection.from_pretrained(CLIP_MODEL_ID, { dtype: DTYPE });

    const promptCounts = rows.map((row) => promptsFor(row, tier).length);
    const embeddings = new Array(rows.length);
    const startedAt = performance.now();
    let done = 0;

    for (let start = 0; start < rows.length; start += EMBED_BATCH_SIZE) {
        const batch = rows.slice(start, start + EMBED_BATCH_SIZE);
        const flatPrompts = batch.flatMap((row) => promptsFor(row, tier));
        const inputs = tokenizer(flatPrompts, { padding: true, truncation: true });
        const output = await model(inputs);
        const dims = output.text_embeds.dims;
        const data = output.text_embeds.data;
        if (dims[0] !== flatPrompts.length || dims[1] !== EMBEDDING_DIM) {
            throw new Error(`Unexpected text_embeds dims ${JSON.stringify(dims)}`);
        }

        let promptIndex = 0;
        for (let rowIndex = 0; rowIndex < batch.length; rowIndex++) {
            const count = promptCounts[start + rowIndex];
            const averaged = new Array(EMBEDDING_DIM).fill(0);
            for (let p = 0; p < count; p++) {
                for (let column = 0; column < EMBEDDING_DIM; column++) {
                    averaged[column] += data[(promptIndex + p) * EMBEDDING_DIM + column];
                }
            }
            for (let column = 0; column < EMBEDDING_DIM; column++) averaged[column] /= count;
            embeddings[start + rowIndex] = normalize(averaged);
            promptIndex += count;
        }

        done += batch.length;
        if (done >= 500) {
            const elapsed = performance.now() - startedAt;
            const perRow = elapsed / done;
            console.log(`  embedded ${done}/${rows.length} (${Math.round(perRow)} ms/row, ~${Math.round((rows.length - done) * perRow / 1000)} s remaining)`);
        }
    }

    return embeddings;
}

function writeTier(tier, rows, embeddings) {
    const isTier1 = tier === 1;
    const encoded = isTier1 ? { buffer: encodeFloat16(embeddings), scales: null } : encodeInt8(embeddings);
    const binary = encoded.buffer;
    const scales = encoded.scales;
    const checksum = sha256(binary);
    const baseName = isTier1 ? 'animal-vocabulary' : 'animal-vocabulary-extended';
    const binName = isTier1 ? `${baseName}.f16.bin` : `${baseName}.i8.bin`;
    const metadataPath = join(publicDataDir, `${baseName}.json`);
    const binPath = join(publicDataDir, binName);

    writeFileSync(binPath, binary);

    const entries = rows.map((row, index) => ({
        qid: row.qid,
        common_name: row.common_name,
        group_name: row.group_name,
        scientific_name: row.scientific,
        scientific_slug: row.scientific_slug,
        name_missing: row.name_missing,
        group: row.group,
        group_label: row.group_label,
        group_noun: row.group_noun,
        parent_qid: row.parent_qid,
        parent_name: row.parent_name,
        iucn_qid: row.iucn_qid,
        iucn_status: row.iucn_status,
        wikipedia_url: row.wikipedia_url,
        row: index,
    }));

    const metadata = {
        version: OUTPUT_VERSION,
        model: CLIP_MODEL_ID,
        dtype: isTier1 ? 'float16' : 'int8',
        dim: EMBEDDING_DIM,
        count: rows.length,
        format: isTier1 ? 'row-major-float16-le' : 'row-major-int8-le',
        binary: binName,
        bytes: binary.length,
        sha256: checksum,
        extended_max: isTier1 ? null : rows.length,
        scales: isTier1 ? null : scales,
        templates: isTier1 ? TIER1_TEMPLATES : TIER2_TEMPLATES,
        attribution: 'Taxonomy data from Wikidata (CC0). Wikipedia text is CC BY-SA 4.0.',
        generated_at: new Date().toISOString(),
        entries,
    };

    writeFileSync(metadataPath, `${JSON.stringify(metadata)}\n`);
    console.log(`Wrote ${metadataPath} (${(statSync(metadataPath).size / 1024).toFixed(1)} KiB)`);
    console.log(`Wrote ${binPath} (${(binary.length / 1024 / 1024).toFixed(2)} MiB)`);
}

/* ------------------------------------------------------------------ *
 * Run
 * ------------------------------------------------------------------ */

const marine = await marineClosure();
const birds = await birdSpeciesSet();
const rowsByGroup = {};

for (const group of selectedGroups) {
    console.log(`Collecting ${group}...`);
    const accept = (row) => {
        if (marine.has(row.qid) || EXCLUDED_TAXA.has(row.qid)) {
            return false;
        }
        if (group === 'reptiles' && birds.has(row.qid)) {
            return false;
        }
        return true;
    };
    const pool = group === 'insects'
        ? await collectAnchorPool(group, INSECT_FALLBACK_ANCHORS, INSECT_ANCHOR_LIMIT, accept)
        : group === 'arachnids'
            ? await collectAnchorPool(group, ARACHNID_ANCHORS, ARACHNID_ANCHOR_LIMIT, accept)
            : await collectGroupPool(group, accept);
    const metadata = await collectMetadata(group, pool);
    const rows = buildRows(group, metadata, { marine, birds });
    rowsByGroup[group] = rows;
    console.log(`${group}: ${rows.length} usable species (from ${metadata.length} resolved)`);
    await sleep(500);
}

if (tierArg === '1' || tierArg === 'both') {
    const tier1Rows = selectTier1(rowsByGroup);
    console.log(`Tier 1: ${tier1Rows.length} species`);
    const embeddings = await embedRows(tier1Rows, 1);
    writeTier(1, tier1Rows, embeddings);
}

if (tierArg === '2' || tierArg === 'both') {
    const tier1Rows = selectTier1(rowsByGroup);
    const tier1Qids = new Set(tier1Rows.map((row) => row.qid));
    const tier2Rows = selectTier2(rowsByGroup, tier1Qids);
    console.log(`Tier 2: ${tier2Rows.length} species`);
    const embeddings = await embedRows(tier2Rows, 2);
    writeTier(2, tier2Rows, embeddings);
}

