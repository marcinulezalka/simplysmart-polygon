#!/usr/bin/env node
/**
 * This file is part of simplysmart/polygon.
 *
 * Copyright (c) 2026 Marcin Ulezalka <marcin.ulezalka@simplysmart.pl>
 *
 * Licensed under the Simplysmart Restricted Source License 1.0.
 * See the LICENSE file distributed with this source code.
 */

/**
 * Orkiestrator assetów: `node vendor/simplysmart/polygon/bin/assets.mjs {build|dev}` z katalogu projektu
 * (skrypty `npm run build` / `npm run dev`).
 *
 * Paczka z assetami ma w katalogu głównym `vite.config.js` eksportujący zwykły obiekt konfiguracji Vite (bez importu
 * `vite` — paczki leżą poza projektem i nie widzą jego `node_modules`), np.
 * `export default { build: { rollupOptions: { input: ['resources/scss/app.scss'] } } }`. Orkiestrator znajduje paczki
 * w `vendor/simplysmart/*`, używa Vite (i preprocesorów, np. `sass`) zainstalowanych raz w projekcie i buduje każdą do
 * jej `public/build/` z `manifest.json` (Assets\PackageAssets, adresy `/vendor/<paczka>/build/`), a na końcu projekt
 * jego własnym `vite.config.js`. `build`: kolejno, błąd dowolnego builda kończy proces kodem 1. `dev`: paczki
 * w trybie watch (przebudowa przy zmianie) równolegle z serwerem dev Vite projektu.
 */

import { spawn } from 'node:child_process';
import { existsSync, readdirSync, readFileSync, realpathSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { pathToFileURL } from 'node:url';

const project = process.cwd();
const mode = process.argv[2];
const PACKAGES = join(project, 'vendor', 'simplysmart');
const CONFIG = 'vite.config.js';

if (mode !== 'build' && mode !== 'dev') {
    console.error('Użycie: node vendor/simplysmart/polygon/bin/assets.mjs {build|dev}');
    process.exit(2);
}

/** Moduł ESM Vite z node_modules projektu (wejście `import` z `exports` paczki vite). */
async function loadVite() {
    const manifestPath = join(project, 'node_modules', 'vite', 'package.json');

    if (!existsSync(manifestPath)) {
        throw new Error('Brak node_modules/vite w projekcie — uruchom npm install.');
    }

    const manifest = JSON.parse(readFileSync(manifestPath, 'utf8'));
    const root = manifest.exports?.['.'];
    const entry = typeof root === 'string' ? root : (root?.import?.default ?? root?.import ?? root?.default ?? manifest.module ?? manifest.main);

    return import(pathToFileURL(join(dirname(manifestPath), entry)).href);
}

/** Paczki z `vite.config.js`: nazwa (katalog w vendor/simplysmart) i rzeczywista ścieżka. */
function packages() {
    if (!existsSync(PACKAGES)) {
        return [];
    }

    return readdirSync(PACKAGES)
        .filter((name) => existsSync(join(PACKAGES, name, CONFIG)))
        .sort()
        .map((name) => ({ name, root: realpathSync(join(PACKAGES, name)) }));
}

/** Konfiguracja Vite paczki scalona z ustawieniami builda do jej public/build. */
async function packageConfig(vite, { name, root }, watch) {
    const own = (await import(pathToFileURL(join(root, CONFIG)).href + `?t=${Date.now()}`)).default ?? {};

    return vite.mergeConfig(own, {
        configFile: false,
        root,
        base: `/vendor/${name}/build/`,
        logLevel: 'info',
        build: {
            outDir: join(root, 'public', 'build'),
            emptyOutDir: true,
            manifest: 'manifest.json',
            watch: watch ? {} : null,
        },
    });
}

/** Proces Vite projektu (`vite build` albo serwer dev). */
function projectVite(args) {
    const bin = join(project, 'node_modules', '.bin', process.platform === 'win32' ? 'vite.cmd' : 'vite');

    return spawn(bin, args, { cwd: project, stdio: 'inherit' });
}

const vite = await loadVite();
const found = packages();

if (mode === 'build') {
    for (const pkg of found) {
        console.log(`\n▸ ${pkg.name}`);
        await vite.build(await packageConfig(vite, pkg, false));
    }

    console.log('\n▸ projekt');
    const child = projectVite(['build']);
    child.on('exit', (code) => process.exit(code ?? 1));
} else {
    for (const pkg of found) {
        await vite.build(await packageConfig(vite, pkg, true));
    }

    const child = projectVite([]);
    const stop = () => child.kill('SIGINT');
    process.on('SIGINT', stop);
    process.on('SIGTERM', stop);
    child.on('exit', (code) => process.exit(code ?? 0));
}
