/**
 * This file is part of simplysmart/polygon.
 *
 * Copyright (c) 2026 Marcin Ulezalka <marcin.ulezalka@simplysmart.pl>
 *
 * Licensed under the Simplysmart Restricted Source License 1.0.
 * See the LICENSE file distributed with this source code.
 */

/**
 * Layout dokumentacji: tryb koloru i rozmiar tekstu (ciasteczka wspólne ze strefami), wysuwany spis na wąskim ekranie,
 * bieżący nagłówek w spisie treści, kopiowanie kodu i wysokość ramek przykładów. Bez JS strona zostaje czytelna.
 */
const YEAR = 31_536_000;
const root = document.documentElement;
const body = document.body;
const examples = [...document.querySelectorAll('[data-pg-doc-example]')];

const fitExample = (frame) => {
    const inner = frame.contentDocument;
    if (inner?.body) {
        frame.style.height = `${inner.body.scrollHeight + 2}px`;
    }
};

const syncExample = (frame) => {
    const inner = frame.contentDocument?.documentElement;
    if (inner) {
        Object.assign(inner.dataset, { theme: root.dataset.theme, fontSize: root.dataset.fontSize });
        fitExample(frame);
    }
};

examples.forEach((frame) => {
    frame.addEventListener('load', () => syncExample(frame));
    syncExample(frame);
});
window.addEventListener('resize', () => examples.forEach(fitExample));

document.querySelectorAll('[data-pg-doc-preference]').forEach((select) => select.addEventListener('change', () => {
    root.dataset[select.dataset.pgDocPreference] = select.value;
    document.cookie = `${select.dataset.pgDocCookie}=${encodeURIComponent(select.value)}; path=/; max-age=${YEAR}; samesite=lax`;
    examples.forEach(syncExample);
}));
document.querySelector('[data-pg-doc-settings]')?.removeAttribute('hidden');

const menu = document.querySelector('[data-pg-doc-menu]');
const header = document.querySelector('.pg-doc__header');
if (menu) {
    const setOpen = (open) => {
        root.style.setProperty('--pg-doc-header', `${header.offsetHeight}px`);
        body.classList.toggle('is-menu-open', open);
        menu.setAttribute('aria-expanded', String(open));
    };
    body.classList.add('has-menu');
    menu.hidden = false;
    menu.addEventListener('click', () => setOpen(!body.classList.contains('is-menu-open')));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && body.classList.contains('is-menu-open')) {
            setOpen(false);
            menu.focus();
        }
    });
    document.getElementById('pg-doc-content')?.addEventListener('click', () => setOpen(false));
}

const index = document.getElementById('pg-doc-index');
const currentPage = index?.querySelector('[aria-current]');
if (currentPage && currentPage.offsetTop > index.clientHeight) {
    index.scrollTop = currentPage.offsetTop - index.clientHeight / 2;
}

const links = new Map([...document.querySelectorAll('.pg-doc__toc a')].map((link) => [decodeURIComponent(link.hash.slice(1)), link]));
const headings = [...links.keys()].map((id) => document.getElementById(id)).filter(Boolean);
const markHeading = () => {
    const limit = header.offsetHeight + 32;
    const current = headings.filter((heading) => heading.getBoundingClientRect().top <= limit).pop() ?? headings[0];
    links.forEach((link) => link.removeAttribute('aria-current'));
    links.get(current?.id)?.setAttribute('aria-current', 'true');
};
if (headings.length > 0) {
    document.addEventListener('scroll', markHeading, { passive: true });
    markHeading();
}

const copyLabel = body.dataset.pgDocCopy;
if (copyLabel && navigator.clipboard) {
    document.querySelectorAll('.pg-doc-content pre').forEach((pre) => {
        const code = pre.querySelector('code');
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'pg-doc__copy';
        button.textContent = copyLabel;
        button.addEventListener('click', async () => {
            await navigator.clipboard.writeText(code.textContent);
            button.textContent = body.dataset.pgDocCopied;
            setTimeout(() => { button.textContent = copyLabel; }, 2000);
        });
        pre.append(button);
    });
}
