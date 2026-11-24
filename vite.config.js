/**
 * This file is part of simplysmart/polygon.
 *
 * Copyright (c) 2026 Marcin Ulezalka <marcin.ulezalka@simplysmart.pl>
 *
 * Licensed under the Simplysmart Restricted Source License 1.0.
 * See the LICENSE file distributed with this source code.
 */

/**
 * Assety polygonu (układ toastu i dzwonka powiadomień — Notifications\NotificationAssets) budowane orkiestratorem
 * (`npm run build` / `npm run dev` w projekcie, `bin/assets.mjs`) do `src/public/build/` (commitowany). Zwykły obiekt
 * konfiguracji Vite, bez importu `vite` — zależności są w projekcie; `root: 'src'` — wejścia i klucze manifestu liczą się
 * od `src/`. `resources/js/notifications.js` nie jest
 * wejściem — strefy importują z niego `initNotifications()` we własnym wejściu JS (NotificationAssets); tak samo `pinpad.js`. Wejścia
 * `documentation*` to layout Wiki, Docs i Preview (Documentation\DocumentationAssets).
 */
export default {
    root: 'src',
    publicDir: false,
    build: {
        rollupOptions: {
            input: [
                'resources/scss/notifications.scss',
                'resources/scss/floating-panel.scss',
                'resources/scss/pinpad.scss',
                'resources/scss/documentation/app.scss',
                'resources/scss/documentation/example.scss',
                'resources/scss/polygon/components.scss',
                'resources/scss/polygon/theme.scss',
                'resources/scss/polygon/wcag/theme.scss',
                'resources/scss/polygon/wcag-contrast/theme.scss',
                'resources/js/documentation.js',
                'resources/js/documentation-example.js',
            ],
        },
    },
};
