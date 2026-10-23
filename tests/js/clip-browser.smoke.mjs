/**
 * Browser smoke test for public/js/clip-loader.js + public/js/scanner-logic.js.
 *
 * Starts a tiny static server for the repo (models, vendored bundle, data,
 * public/js), opens a headless Chrome/Edge via puppeteer-core, loads the CLIP
 * vision encoder in local mode, classifies a synthetic canvas and runs the
 * decision rules end to end.
 *
 * Run with: npm run test:browser
 * Set CHROME_PATH to override browser detection.
 */
import { createServer } from 'node:http';
import { existsSync, readFileSync, statSync } from 'node:fs';
import { extname, join, normalize } from 'node:path';
import { fileURLToPath } from 'node:url';
import { dirname } from 'node:path';
import puppeteer from 'puppeteer-core';

const repoRoot = join(dirname(fileURLToPath(import.meta.url)), '..', '..');

const MIME = {
    '.html': 'text/html; charset=utf-8',
    '.js': 'text/javascript; charset=utf-8',
    '.mjs': 'text/javascript; charset=utf-8',
    '.json': 'application/json; charset=utf-8',
    '.onnx': 'application/octet-stream',
    '.wasm': 'application/wasm',
};

const SMOKE_HTML = `<!doctype html>
<html>
<head><meta charset="utf-8"><title>CLIP smoke</title></head>
<body>
<script type="module">
    import * as loader from '/js/clip-loader.js';
    await import('/js/scanner-logic.js');
    window.clipSmoke = { loader, Logic: window.EcoSightLogic };
    window.clipSmokeReady = true;
</script>
</body>
</html>`;

function startServer() {
    const server = createServer((request, response) => {
        const url = new URL(request.url, 'http://127.0.0.1');
        if (url.pathname === '/__smoke.html') {
            response.writeHead(200, { 'content-type': MIME['.html'] });
            response.end(SMOKE_HTML);
            return;
        }

        if (url.pathname === '/favicon.ico') {
            response.writeHead(204);
            response.end();
            return;
        }

        const pathname = normalize(decodeURIComponent(url.pathname)).replace(/^([/\\])+/, '');
        const webRoot = /^(js|data|models|vendor)[/\\]/.test(pathname) ? join(repoRoot, 'public') : repoRoot;
        const filePath = join(webRoot, pathname);
        if (!filePath.startsWith(repoRoot) || !existsSync(filePath) || !statSync(filePath).isFile()) {
            response.writeHead(404);
            response.end('not found');
            return;
        }

        response.writeHead(200, {
            'content-type': MIME[extname(filePath).toLowerCase()] || 'application/octet-stream',
            'content-length': statSync(filePath).size,
        });
        if (request.method === 'HEAD') {
            response.end();
            return;
        }
        response.end(readFileSync(filePath));
    });

    return new Promise((resolve) => {
        server.listen(0, '127.0.0.1', () => {
            resolve({ server, port: server.address().port });
        });
    });
}

function findBrowser() {
    const candidates = [
        process.env.CHROME_PATH,
        'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
        'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
        'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
        'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
    ].filter(Boolean);

    const found = candidates.find((candidate) => existsSync(candidate));
    if (!found) {
        throw new Error('No Chrome/Edge found. Set CHROME_PATH.');
    }
    return found;
}

const failures = [];
const check = (condition, message) => {
    if (!condition) failures.push(message);
    console.log(`${condition ? 'PASS' : 'FAIL'}  ${message}`);
};

const { server, port } = await startServer();
const browser = await puppeteer.launch({
    executablePath: findBrowser(),
    headless: true,
    args: ['--no-sandbox', '--disable-dev-shm-usage'],
});

try {
    const page = await browser.newPage();
    page.setDefaultTimeout(180000);
    const consoleErrors = [];
    const pageErrors = [];
    const modelRequests = [];
    page.on('console', (message) => {
        if (message.type() === 'error') consoleErrors.push(message.text());
    });
    page.on('pageerror', (error) => pageErrors.push(String(error)));
    page.on('request', (request) => {
        const url = request.url();
        if (url.includes('vision_model') || url.includes('text_model')) modelRequests.push(url);
    });
    const missingResources = [];
    page.on('response', (response) => {
        if (response.status() >= 400) missingResources.push(`${response.status()} ${response.url()}`);
    });

    await page.goto(`http://127.0.0.1:${port}/__smoke.html`);
    await page.waitForFunction('window.clipSmokeReady === true');
    console.log('Smoke page ready.');

    const result = await page.evaluate(async () => {
        const { loader, Logic } = window.clipSmoke;

        let shareProgressEvents = 0;
        const startedAt = performance.now();
        const clip = await loader.loadClip({
            onProgress: (payload) => {
                if (payload && payload.status === 'progress') shareProgressEvents += 1;
            },
        });
        const loadMs = Math.round(performance.now() - startedAt);

        const embeddings = await loader.loadTextEmbeddings();
        const labels = {
            clipPrompts: {
                animals: Object.fromEntries(Object.entries(embeddings.animals).map(([key, value]) => [key, value.prompts])),
                varieties: Object.fromEntries(Object.entries(embeddings.varieties).map(([key, value]) => [key, value.prompts])),
            },
        };
        const fresh = loader.embeddingsMatchLabels(embeddings, labels);

        const staleLabels = JSON.parse(JSON.stringify(labels));
        staleLabels.clipPrompts.animals.bear = ['a photo of a bear', 'bear', 'a wild bear (edited)'];
        const staleDetected = loader.embeddingsMatchLabels(embeddings, staleLabels);

        const canvas = document.createElement('canvas');
        canvas.width = 320;
        canvas.height = 320;
        const context = canvas.getContext('2d');
        const gradient = context.createLinearGradient(0, 0, 320, 320);
        gradient.addColorStop(0, '#7a5230');
        gradient.addColorStop(1, '#c8b38a');
        context.fillStyle = gradient;
        context.fillRect(0, 0, 320, 320);
        context.fillStyle = '#3b2a1a';
        context.beginPath();
        context.ellipse(160, 190, 95, 60, 0, 0, Math.PI * 2);
        context.fill();

        const inferStart = performance.now();
        const classified = await loader.classifyImage(clip, canvas, { embeddings });
        const inferMs = Math.round(performance.now() - inferStart);

        const varietyScores = loader.scoreVarieties(classified.vector, embeddings, 'dog');
        const decision = Logic.decideFromClip(classified.animals, classified.distractors);

        return {
            mode: clip.mode,
            loadMs,
            inferMs,
            progressEvents: shareProgressEvents,
            counts: {
                animals: Object.keys(embeddings.animals).length,
                varieties: Object.keys(embeddings.varieties).length,
                distractors: embeddings.distractors.length,
                dim: embeddings.dim,
            },
            fresh,
            staleDetected,
            animalScores: classified.animals.length,
            animalProbabilitySum: classified.animals.reduce((sum, entry) => sum + entry.probability, 0),
            distractorProbabilitySum: classified.distractors.reduce((sum, entry) => sum + entry.probability, 0),
            finite: classified.animals.every((entry) => Number.isFinite(entry.probability) && Number.isFinite(entry.similarity)),
            top: classified.animals[0],
            decision: { status: decision.status, rule: decision.rule, label: decision.label || null },
            varietyCount: varietyScores.length,
            varietySumsToOne: Math.abs(varietyScores.reduce((sum, entry) => sum + entry.probability, 0) - 1) < 1e-9,
        };
    });

    console.log('\nBrowser result:', JSON.stringify(result, null, 2));

    check(result.mode === 'local', 'CLIP loads from local /models (mode=local)');
    check(result.counts.dim === 512, 'text embeddings are 512-d');
    check(result.counts.animals === 33, `33 animal embeddings (got ${result.counts.animals})`);
    check(result.counts.varieties === 16, `16 variety embeddings (got ${result.counts.varieties})`);
    check(result.counts.distractors === 14, `14 distractor embeddings (got ${result.counts.distractors})`);
    check(result.fresh === true, 'committed embeddings match fresh labels payload');
    check(result.staleDetected === false, 'edited clip_names are detected as stale');
    check(result.animalScores === 33, 'classification scores every animal');
    check(result.finite, 'all animal scores are finite');
    check(Math.abs(result.animalProbabilitySum - 1) < 1e-6, 'animal probabilities sum to 1');
    check(Math.abs(result.distractorProbabilitySum - 1) < 1e-6, 'distractor probabilities sum to 1');
    check(result.varietyCount === 6, 'variety scoring returns the 6 dog breeds');
    check(result.varietySumsToOne, 'variety probabilities sum to 1');
    check(['clip', 'clip-unsure', 'clip-weak', 'clip-below-threshold', 'distractor', 'none'].includes(result.decision.rule),
        `decision rule is valid (${result.decision.rule})`);
    check(modelRequests.some((url) => url.includes('/models/Xenova/clip-vit-base-patch32/onnx/vision_model_quantized.onnx')),
        'vision model was fetched from /models');
    check(!modelRequests.some((url) => url.includes('huggingface.co')), 'no model request went to huggingface.co');
    check(pageErrors.length === 0, `no page errors (${pageErrors.join('; ') || 'none'})`);
    check(consoleErrors.length === 0, `no console errors (${consoleErrors.join('; ') || 'none'})`);
    check(missingResources.length === 0, `no missing resources (${missingResources.join('; ') || 'none'})`);

    if (failures.length > 0) {
        console.error(`\n${failures.length} assertion(s) failed.`);
        process.exitCode = 1;
    } else {
        console.log('\nCLIP browser smoke test passed.');
    }
} finally {
    await browser.close();
    server.close();
}
