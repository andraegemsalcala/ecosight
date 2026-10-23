/**
 * Download the pinned CLIP model files into public/models/ with curl.
 *
 * Node's fetch cannot complete TLS to huggingface.co on this machine
 * (ECONNRESET; curl/Chrome/PowerShell succeed), and hf-mirror.com
 * redirects straight back to huggingface.co — so every download goes
 * through curl.exe, which works. The files are gitignored
 * (/public/models); the browser then loads them same-origin (local mode)
 * and the Node build tools load them from disk.
 *
 * Layout matches transformers.js: <root>/<model id>/<file>.
 *
 * Usage:
 *   node scripts/download-clip.mjs                # everything
 *   node scripts/download-clip.mjs --vision-only  # skip the text encoder
 */
import { execFileSync } from 'node:child_process';
import { existsSync, mkdirSync, rmSync, statSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

export const CLIP_MODEL_ID = 'Xenova/clip-vit-base-patch32';
export const CLIP_REVISION = 'main';

/** @type {Array<{ path: string, size: number, encoder: 'shared' | 'vision' | 'text' }>} */
const FILES = [
    { path: 'config.json', size: 4524, encoder: 'shared' },
    { path: 'preprocessor_config.json', size: 520, encoder: 'shared' },
    { path: 'special_tokens_map.json', size: 472, encoder: 'shared' },
    { path: 'tokenizer.json', size: 2224119, encoder: 'shared' },
    { path: 'tokenizer_config.json', size: 775, encoder: 'shared' },
    { path: 'vocab.json', size: 862328, encoder: 'shared' },
    { path: 'merges.txt', size: 524619, encoder: 'shared' },
    { path: 'onnx/vision_model_quantized.onnx', size: 89117001, encoder: 'vision' },
    { path: 'onnx/text_model_quantized.onnx', size: 64504507, encoder: 'text' },
];

const repoRoot = join(dirname(fileURLToPath(import.meta.url)), '..');

export const TRANSFORMERS_VERSION = '4.3.0';
const TRANSFORMERS_URL = `https://cdn.jsdelivr.net/npm/@huggingface/transformers@${TRANSFORMERS_VERSION}/dist/transformers.min.js`;
const TRANSFORMERS_MIN_BYTES = 400_000;

/**
 * Download missing CLIP files under `<repo>/public/models`.
 *
 * @param {{ visionOnly?: boolean, root?: string, quiet?: boolean }} [options]
 * @returns {Promise<{ root: string, downloaded: string[], skipped: string[], bytes: number }>}
 */
export async function downloadClipModel(options = {}) {
    const root = options.root ?? join(repoRoot, 'public', 'models');
    const modelDir = join(root, CLIP_MODEL_ID);
    const visionOnly = options.visionOnly === true;
    const wanted = FILES.filter((file) => !(visionOnly && file.encoder === 'text'));

    const downloaded = [];
    const skipped = [];
    let bytes = 0;

    for (const file of wanted) {
        const destination = join(modelDir, file.path);
        if (existsSync(destination) && statSync(destination).size === file.size) {
            skipped.push(file.path);
            bytes += file.size;
            continue;
        }

        mkdirSync(dirname(destination), { recursive: true });
        const url = `https://huggingface.co/${CLIP_MODEL_ID}/resolve/${CLIP_REVISION}/${file.path}`;
        if (!options.quiet) {
            console.log(`Downloading ${file.path} (${(file.size / 1024 / 1024).toFixed(1)} MiB)…`);
        }

        // This network resets TLS mid-transfer repeatedly. curl retries in
        // place; when it finally gives up we resume the partial file with an
        // HTTP range request (outer loop) until the byte count matches.
        let resume = false;
        let lastError = null;
        let previousPartial = -1;
        for (let attempt = 1; attempt <= 8; attempt++) {
            try {
                const args = [
                    '-L', '--fail', '--silent', '--show-error',
                    '--retry', '5', '--retry-delay', '2', '--retry-all-errors',
                    '--connect-timeout', '30',
                ];
                if (resume && existsSync(destination)) args.push('-C', '-');
                args.push('-o', destination, url);
                execFileSync('curl.exe', args, {
                    stdio: options.quiet ? 'ignore' : ['ignore', 'inherit', 'inherit'],
                });
            } catch (error) {
                lastError = error;
            }

            if (existsSync(destination) && statSync(destination).size === file.size) {
                break;
            }
            if (attempt === 8) {
                throw new Error(`${file.path}: download failed after 8 attempts (${lastError?.message || 'size mismatch'})`);
            }
            const partial = existsSync(destination) ? statSync(destination).size : 0;
            if (partial > file.size) {
                rmSync(destination, { force: true });
                resume = false;
            } else {
                // Resume only if the previous attempt actually made progress.
                resume = partial > 0 && partial < file.size && partial > previousPartial;
            }
            previousPartial = partial;
            if (!options.quiet) {
                console.warn(`  attempt ${attempt} incomplete (${partial}/${file.size} bytes); ${resume ? 'resuming' : 'restarting'}…`);
            }
            await new Promise((resolve) => setTimeout(resolve, 1000 * attempt));
        }

        const actual = statSync(destination).size;
        if (actual !== file.size) {
            throw new Error(`${file.path}: expected ${file.size} bytes, got ${actual}`);
        }
        downloaded.push(file.path);
        bytes += actual;
    }

    if (!options.quiet) {
        console.log(`CLIP model ready at ${modelDir}: ${downloaded.length} downloaded, ${skipped.length} already present, ${(bytes / 1024 / 1024).toFixed(1)} MiB total.`);
    }

    return { root, downloaded, skipped, bytes };
}

/**
 * Download the pinned self-contained browser ESM bundle from jsDelivr into
 * public/vendor/transformers/ (gitignored, like the model files).
 *
 * The bundle pins its own onnxruntime-web WASM URLs, so the CDN is needed at
 * runtime for WASM even when the model weights are served locally.
 *
 * @returns {{ path: string, downloaded: boolean }}
 */
export function downloadTransformersBundle(options = {}) {
    const destination = join(repoRoot, 'public', 'vendor', 'transformers', 'transformers.min.js');

    if (existsSync(destination) && statSync(destination).size >= TRANSFORMERS_MIN_BYTES) {
        return { path: destination, downloaded: false };
    }

    mkdirSync(dirname(destination), { recursive: true });
    if (!options.quiet) {
        console.log(`Downloading transformers.min.js (${TRANSFORMERS_VERSION})…`);
    }
    execFileSync('curl.exe', [
        '-L', '--fail', '--silent', '--show-error',
        '--retry', '5', '--retry-delay', '2', '--retry-all-errors',
        '--connect-timeout', '30',
        '-o', destination, TRANSFORMERS_URL,
    ], { stdio: options.quiet ? 'ignore' : ['ignore', 'inherit', 'inherit'] });

    const size = statSync(destination).size;
    if (size < TRANSFORMERS_MIN_BYTES) {
        throw new Error(`transformers.min.js looks truncated (${size} bytes)`);
    }
    if (!options.quiet) {
        console.log(`transformers.min.js ready (${(size / 1024).toFixed(0)} KiB).`);
    }

    return { path: destination, downloaded: true };
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
    const visionOnly = process.argv.includes('--vision-only');
    try {
        await downloadClipModel({ visionOnly });
        downloadTransformersBundle();
    } catch (error) {
        console.error(error.message || error);
        process.exit(1);
    }
}
