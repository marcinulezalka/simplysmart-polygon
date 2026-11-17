/**
 * This file is part of simplysmart/polygon.
 *
 * Copyright (c) 2026 Marcin Ulezalka <marcin.ulezalka@simplysmart.pl>
 *
 * Licensed under the Simplysmart Restricted Source License 1.0.
 * See the LICENSE file distributed with this source code.
 */

/**
 * Pływający panel akcji (docs/strefy.md → „Pływający panel akcji”) — jeden mechanizm dla wszystkich stref. Znaczniki
 * daje widok strefy: korzeń `[data-pg-float]` (klasa `pg-float pg-float--<położenie>`, Enums\FloatingPanelPosition),
 * w nim toasty (`[data-pg-toasts]`, które initNotifications() zapełnia zamiast własnego kontenera) i panel
 * `.pg-float__panel` z przyciskiem `[data-pg-float-toggle]` (w spoczynku sam ten przycisk) i treścią
 * `[data-pg-float-body]` (wyszukiwarka i przyciski z rozwijanymi menu `<details>`, przycisk `[data-pg-float-close]`).
 * Kliknięcie przycisku rozwija panel i ustawia fokus na pierwszym polu; zamyka go tylko `[data-pg-float-close]` albo
 * Esc — panel zostaje otwarty do zmiany widoku, kliknięcie obok nie zamyka go (konto mogło w nim coś zacząć, np.
 * wyszukiwanie). W rozwiniętym panelu otwarte jest najwyżej jedno menu. Bez korzenia nic nie robi.
 *
 * @param {{ root?: string }} [options]
 */
export function initFloatingPanel(options = {}) {
    const { root: rootSelector = '[data-pg-float]' } = options;
    const root = document.querySelector(rootSelector);
    const panel = root?.querySelector('.pg-float__panel');
    const toggle = root?.querySelector('[data-pg-float-toggle]');
    const body = root?.querySelector('[data-pg-float-body]');
    if (!root || !panel || !toggle || !body) {
        return;
    }

    const isOpen = () => root.classList.contains('is-open');
    const menus = () => [...body.querySelectorAll('details')];

    const setOpen = (open) => {
        root.classList.toggle('is-open', open);
        toggle.setAttribute('aria-expanded', String(open));
        toggle.hidden = open;
        body.hidden = !open;
        if (open) {
            body.querySelector('input, summary, button, a')?.focus();
        } else {
            menus().forEach((menu) => { menu.open = false; });
        }
    };

    toggle.addEventListener('click', () => setOpen(true));
    body.querySelectorAll('[data-pg-float-close]').forEach((button) => button.addEventListener('click', () => {
        setOpen(false);
        toggle.focus();
    }));
    menus().forEach((menu) => menu.addEventListener('toggle', () => {
        if (menu.open) {
            menus().forEach((other) => { if (other !== menu) { other.open = false; } });
        }
    }));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && isOpen()) {
            setOpen(false);
            toggle.focus();
        }
    });
    setOpen(false);
}
