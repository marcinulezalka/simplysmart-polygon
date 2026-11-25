/**
 * This file is part of simplysmart/polygon.
 *
 * Copyright (c) 2026 Marcin Ulezalka <marcin.ulezalka@simplysmart.pl>
 *
 * Licensed under the Simplysmart Restricted Source License 1.0.
 * See the LICENSE file distributed with this source code.
 */

/** Czas i przebieg przesunięcia zaznaczenia. */
const SLIDE = { duration: 250, easing: 'cubic-bezier(0.4, 0, 0.2, 1)' };

/** Klasa grupy na czas przesunięcia: kafelki jej pozycji są bez tła, maluje je suwak. */
const SLIDING_CLASS = 'is-sliding';

/**
 * Przesunięcie zaznaczenia w `<x-polygon::sidebar-nav-icon action-slide>` jak w przełączniku: kliknięta pozycja od razu jest bieżąca,
 * a kafelek przesuwa się do niej z pozycji zaznaczonej w tej samej grupie. Grupa bez zaznaczenia (także z jedną pozycją) i system
 * z ograniczonym ruchem — samo zaznaczenie. Link działa jak zwykle: przejścia na stronę skrypt nie zatrzymuje ani nie opóźnia,
 * a kliknięcie otwierające nową kartę niczego nie zaznacza. Powrót do strony z pamięci przeglądarki przywraca zaznaczenie serwera.
 *
 * @param {{ root?: string }} [options]
 */
export function initSidebarNavIcon(options = {}) {
    const { root: rootSelector = '[data-pg-sidebar-nav-icon-slide]' } = options;
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    document.querySelectorAll(rootSelector).forEach((root) => {
        const items = [...root.querySelectorAll('.pg-sidebar-nav-icon__item')];
        const initial = items.find((item) => item.hasAttribute('aria-current'));
        let thumb = null;

        const stop = () => {
            thumb?.parentElement.classList.remove(SLIDING_CLASS);
            thumb?.remove();
            thumb = null;
        };

        const select = (selected) => items.forEach((item) => {
            if (item === selected) {
                item.setAttribute('aria-current', 'page');
            } else {
                item.removeAttribute('aria-current');
            }
        });

        // Suwak stoi na kafelku docelowym (pierwszy element pozycji: ikona albo avatar) i rusza z miejsca `startTop`, więc po animacji nie trzeba go przestawiać.
        const slide = (group, startTop, target) => {
            const end = target.getBoundingClientRect();
            const box = group.getBoundingClientRect();
            const current = document.createElement('span');
            current.className = 'pg-sidebar-nav-icon__thumb';
            current.setAttribute('aria-hidden', 'true');
            current.style.top = `${end.top - box.top - group.clientTop}px`;
            current.style.left = `${end.left - box.left - group.clientLeft}px`;
            group.classList.add(SLIDING_CLASS);
            group.append(current);
            thumb = current;
            current.animate([{ transform: `translateY(${startTop - end.top}px)` }, { transform: 'none' }], SLIDE).finished.then(() => {
                if (thumb === current) {
                    stop();
                }
            }, () => {});
        };

        root.addEventListener('click', (event) => {
            const item = event.target.closest('.pg-sidebar-nav-icon__item');
            if (!item || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || item.target === '_blank' || item.hasAttribute('aria-current')) {
                return;
            }

            const group = item.closest('.pg-sidebar-nav-icon__group');
            const previous = group.querySelector('.pg-sidebar-nav-icon__item[aria-current]')?.firstElementChild;
            const startTop = previous && (thumb ?? previous).getBoundingClientRect().top;
            stop();
            select(item);
            if (previous && !reducedMotion.matches) {
                slide(group, startTop, item.firstElementChild);
            }
        });

        window.addEventListener('pageshow', (event) => {
            if (event.persisted) {
                stop();
                select(initial);
            }
        });
    });
}
