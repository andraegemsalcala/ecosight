import { execFileSync } from 'node:child_process';
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));

export const CLIP_TOOL_DIR = join(here, '.clip-tool');
export const TRANSFORMERS_VERSION = '4.3.0';

/**
 * Install the pinned Transformers.js toolchain into scripts/.clip-tool
 * (gitignored, outside the project dependency tree) on first use, then
 * resolve the package's Node entry point. Nothing in package.json or
 * package-lock.json is touched.
 *
 * @returns {{ toolDir: string, transformersEntry: string, version: string }}
 */
export function ensureClipTool() {
    const manifestPath = join(CLIP_TOOL_DIR, 'package.json');
    const installedManifest = join(
        CLIP_TOOL_DIR,
        'node_modules',
        '@huggingface',
        'transformers',
        'package.json'
    );

    let installedVersion = null;
    if (existsSync(installedManifest)) {
        installedVersion = JSON.parse(readFileSync(installedManifest, 'utf8')).version;
    }

    if (installedVersion !== TRANSFORMERS_VERSION) {
        mkdirSync(CLIP_TOOL_DIR, { recursive: true });
        writeFileSync(
            manifestPath,
            JSON.stringify({ name: 'ecosight-clip-tool', private: true, type: 'module' }, null, 4) + '\n'
        );

        console.log(`clip-tool: installing @huggingface/transformers@${TRANSFORMERS_VERSION} (one-off, ~300 MB)…`);
        const args = ['install', '--no-audit', '--no-fund', `@huggingface/transformers@${TRANSFORMERS_VERSION}`];
        if (process.platform === 'win32') {
            execFileSync(process.env.ComSpec || 'cmd.exe', ['/d', '/s', '/c', 'npm', ...args], {
                cwd: CLIP_TOOL_DIR,
                stdio: 'inherit',
            });
        } else {
            execFileSync('npm', args, { cwd: CLIP_TOOL_DIR, stdio: 'inherit' });
        }
        installedVersion = JSON.parse(readFileSync(installedManifest, 'utf8')).version;
    }

    const entry = join(CLIP_TOOL_DIR, 'node_modules', '@huggingface', 'transformers', 'dist', 'transformers.node.mjs');
    if (!existsSync(entry)) {
        throw new Error(`clip-tool: expected Node bundle at ${entry}`);
    }

    return {
        toolDir: CLIP_TOOL_DIR,
        transformersEntry: entry,
        version: installedVersion,
    };
}

/**
 * Import the pinned Transformers.js Node build.
 *
 * @returns {Promise<Record<string, any>>}
 */
export async function importTransformers() {
    const { transformersEntry } = ensureClipTool();
    return import(pathToFileURL(transformersEntry).href);
}

/**
 * Ensure the CLIP model files exist under public/models (curl-based
 * download; see scripts/download-clip.mjs for why curl is used).
 *
 * @param {{ visionOnly?: boolean }} [options]
 * @returns {{ root: string, modelDir: string }}
 */
export async function ensureClipModel(options = {}) {
    const { downloadClipModel, downloadTransformersBundle, CLIP_MODEL_ID } = await import('./download-clip.mjs');
    const { root } = await downloadClipModel({ visionOnly: options.visionOnly === true, quiet: true });
    downloadTransformersBundle({ quiet: true });
    return { root, modelDir: join(root, CLIP_MODEL_ID) };
}

/**
 * Point Transformers.js at local model files only (no remote fetches).
 *
 * @param {Record<string, any>} transformers
 * @param {string} modelsRoot absolute path of public/models
 */
export function useLocalModels(transformers, modelsRoot) {
    transformers.env.allowLocalModels = true;
    transformers.env.allowRemoteModels = false;
    transformers.env.localModelPath = modelsRoot.endsWith('/') ? modelsRoot : `${modelsRoot}/`;
}
