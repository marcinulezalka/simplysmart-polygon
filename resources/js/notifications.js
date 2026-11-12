/**
 * This file is part of simplysmart/polygon.
 *
 * Copyright (c) 2026 Marcin Ulezalka <marcin.ulezalka@simplysmart.pl>
 *
 * Licensed under the Simplysmart Restricted Source License 1.0.
 * See the LICENSE file distributed with this source code.
 */

/**
 * Toast (dolny prawy róg) i lista w dzwonku paska nawigacji — jeden mechanizm dla wszystkich stref. Treść i rodzaj
 * (status/danger) daje Notifications\FlashNotifications (partials.messages strefy, znacznik data-pg-flash z listą
 * {text, kind}); historia (localStorage, do `limit` wpisów) i licznik nieprzeczytanych są osobne dla każdej strefy
 * (`zone` w opcjach), bo jedna domena może obsługiwać więcej stref. Znaczniki — klasy `pg-*` i atrybuty `data-pg-*`
 * — są wspólne (resources/scss/notifications.scss, układ bez kolorów); kolor, obramowanie i cień tych klas maluje
 * arkusz strefy, która je ładuje na swoich stronach. Strefa woła initNotifications() ze swojego wejścia JS i podaje
 * tylko to, co jest jej: ikony (Enums\Icon strefy) i nazwę strefy — ten moduł nie zna żadnego zestawu ikon.
 *
 * @param {{
 *     zone: string,
 *     icons: { status: string, danger: string, close: string },
 *     bell?: string,
 *     autoHideMs?: number,
 *     limit?: number,
 * }} options
 */
export function initNotifications(options) {
    const { zone, icons, bell: bellSelector = '[data-pg-bell]', autoHideMs = 6000, limit = 20 } = options;
    const STORE_KEY = `pg.${zone}.notifications`;
    const SEEN_KEY = `pg.${zone}.notifications.seen`;

    const readStore = () => {
        try {
            const parsed = JSON.parse(localStorage.getItem(STORE_KEY) ?? '[]');

            return Array.isArray(parsed) ? parsed : [];
        } catch {
            return [];
        }
    };

    const writeStore = (entries) => {
        try {
            localStorage.setItem(STORE_KEY, JSON.stringify(entries));
        } catch {
            // localStorage niedostępny (prywatne okno, zablokowane dane witryny) — toast i tak się pokaże, historia dzwonka po prostu nie zapamięta wpisu.
        }
    };

    const seenAt = () => {
        try {
            return Number(localStorage.getItem(SEEN_KEY)) || 0;
        } catch {
            return 0;
        }
    };

    const markSeen = () => {
        try {
            localStorage.setItem(SEEN_KEY, String(Date.now()));
        } catch {
            // jak wyżej.
        }
    };

    const timeFormat = new Intl.DateTimeFormat(document.documentElement.lang || undefined, { hour: '2-digit', minute: '2-digit' });

    let toasts = null;

    const toastContainer = () => {
        if (!toasts) {
            toasts = document.createElement('div');
            toasts.className = 'pg-toasts';
            toasts.setAttribute('aria-live', 'polite');
            document.body.append(toasts);
        }

        return toasts;
    };

    const icon = (name, className) => {
        const element = document.createElement('iconify-icon');
        element.setAttribute('icon', name);
        element.className = className;
        element.setAttribute('aria-hidden', 'true');

        return element;
    };

    const showToast = (text, kind) => {
        const toast = document.createElement('div');
        toast.className = `pg-toast pg-toast--${kind}`;
        if (kind === 'danger') {
            toast.setAttribute('role', 'alert');
        }
        const body = document.createElement('div');
        body.className = 'pg-toast__body';
        body.textContent = text;
        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'pg-toast__close';
        close.setAttribute('aria-label', 'Zamknij');
        close.append(icon(icons.close, 'pg-icon'));
        toast.append(icon(kind === 'danger' ? icons.danger : icons.status, 'pg-icon pg-toast__icon'), body, close);

        const dismiss = () => {
            toast.classList.add('is-leaving');
            toast.addEventListener('animationend', () => toast.remove(), { once: true });
        };
        close.addEventListener('click', dismiss);
        const timeout = setTimeout(dismiss, autoHideMs);
        toast.addEventListener('mouseenter', () => clearTimeout(timeout));

        toastContainer().append(toast);
    };

    const renderBell = () => {
        const list = document.querySelector('[data-pg-notifications-list]');
        const empty = document.querySelector('[data-pg-notifications-empty]');
        const badge = document.querySelector('[data-pg-bell-badge]');
        if (!list || !empty || !badge) {
            return;
        }

        const entries = readStore();
        const unread = entries.filter((entry) => entry.time > seenAt()).length;
        badge.hidden = unread === 0;
        badge.textContent = unread > 9 ? '9+' : String(unread);

        list.replaceChildren();
        entries.forEach((entry) => {
            const item = document.createElement('li');
            item.className = `pg-notifications__item pg-notifications__item--${entry.kind}`;
            const text = document.createElement('span');
            text.className = 'pg-notifications__text';
            text.textContent = entry.text;
            const time = document.createElement('span');
            time.className = 'pg-notifications__time';
            time.textContent = timeFormat.format(new Date(entry.time));
            item.append(text, time);
            list.append(item);
        });
        empty.hidden = entries.length > 0;
    };

    const notify = (text, kind) => {
        if (!text) {
            return;
        }
        showToast(text, kind);
        writeStore([{ id: `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`, text, kind, time: Date.now() }, ...readStore()].slice(0, limit));
        renderBell();
    };

    document.querySelectorAll('[data-pg-flash]').forEach((node) => {
        let payload;
        try {
            payload = JSON.parse(node.textContent || '[]');
        } catch {
            payload = [];
        }
        (Array.isArray(payload) ? payload : []).forEach((entry) => {
            if (entry && typeof entry.text === 'string' && entry.text !== '' && (entry.kind === 'status' || entry.kind === 'danger')) {
                notify(entry.text, entry.kind);
            }
        });
        node.remove();
    });

    const bell = document.querySelector(bellSelector);
    if (bell) {
        renderBell();
        bell.addEventListener('toggle', () => {
            if (bell.open) {
                markSeen();
                renderBell();
            }
        });
    }
}
