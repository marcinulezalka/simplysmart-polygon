/**
 * This file is part of simplysmart/polygon.
 *
 * Copyright (c) 2026 Marcin Ulezalka <marcin.ulezalka@simplysmart.pl>
 *
 * Licensed under the Simplysmart Restricted Source License 1.0.
 * See the LICENSE file distributed with this source code.
 */

/**
 * Klawiatura numeryczna pola PIN-u (docs/strefy.md → „Klawiatura numeryczna PIN-u”) — jeden mechanizm dla wszystkich stref.
 * Korzeń `[data-pg-pinpad]` obejmuje pole, przycisk `[data-pg-pinpad-toggle]` i klawisze `<x-polygon::pinpad>`
 * (`[data-pg-pinpad-keys]`). Przycisk pokazuje i chowa klawisze; cyfra trafia na koniec pola (do jego `maxlength`), „C” czyści
 * pole, „OK” to zwykły przycisk wysyłający formularz. Otwarta klawiatura wyłącza systemową (`inputmode="none"`), żeby na
 * telefonie nie było dwóch naraz, a naciśnięcie klawisza myszą lub palcem nie zabiera fokusu polu.
 *
 * @param {{ root?: string }} [options]
 */
export function initPinpad(options = {}) {
    const { root: rootSelector = '[data-pg-pinpad]' } = options;

    const setValue = (input, value) => {
        input.value = value;
        input.dispatchEvent(new Event('input', { bubbles: true }));
    };

    document.querySelectorAll(rootSelector).forEach((root) => {
        const input = root.querySelector('input');
        const toggle = root.querySelector('[data-pg-pinpad-toggle]');
        const keys = root.querySelector('[data-pg-pinpad-keys]');
        if (!input || !toggle || !keys) {
            return;
        }

        const inputMode = input.inputMode;
        toggle.hidden = false;
        toggle.addEventListener('click', () => {
            keys.hidden = !keys.hidden;
            toggle.setAttribute('aria-expanded', String(!keys.hidden));
            input.inputMode = keys.hidden ? inputMode : 'none';
        });
        keys.addEventListener('pointerdown', (event) => event.preventDefault());
        keys.addEventListener('click', (event) => {
            const key = event.target.closest('button');
            if (!key) {
                return;
            }

            if ('pgPinpadClear' in key.dataset) {
                setValue(input, '');
            } else if ('pgPinpadDigit' in key.dataset && (input.maxLength < 0 || input.value.length < input.maxLength)) {
                setValue(input, input.value + key.dataset.pgPinpadDigit);
            }
        });
    });
}
