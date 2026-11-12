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
 * (`npm run build` / `npm run dev` w projekcie, `bin/assets.mjs`) do `public/build/` (commitowany). Zwykły obiekt
 * konfiguracji Vite, bez importu `vite` — zależności są w projekcie. `resources/js/notifications.js` nie jest
 * wejściem — strefy importują z niego `initNotifications()` we własnym wejściu JS (NotificationAssets).
 */
export default {
    publicDir: false,
    build: {
        rollupOptions: {
            input: [
                'resources/scss/notifications.scss',
            ],
        },
    },
};
