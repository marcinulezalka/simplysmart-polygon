# Assety paczek i tryby kolorystyczne

## Build: Vite w każdej paczce

Jedno `npm run build` / `npm run dev` w projekcie buduje assety wszystkich paczek z `vendor/simplysmart/*` i projektu.
Skrypty projektu wołają orkiestrator `node vendor/simplysmart/polygon/bin/assets.mjs {build|dev}`.

- **Paczka:** w katalogu głównym `vite.config.js` eksportujący zwykły obiekt konfiguracji Vite, bez importu `vite`
  (paczki leżą poza projektem i nie widzą jego `node_modules`), np.
  `export default { build: { rollupOptions: { input: ['resources/scss/app.scss'] } } }`. Źródła w
  `resources/scss/`, `resources/js/`. Opcjonalny `package.json` (`"private": true`, `"name": "@simplysmart/<paczka>"`)
  tylko jako opis — zależności npm są w projekcie.
- **Orkiestrator:** Vite i preprocesory (np. `sass`) z `node_modules` projektu; każda paczka trafia do swojego
  `public/build/` z `manifest.json` i bazą `/vendor/<paczka>/build/`. `build` buduje paczki po kolei, a na końcu
  projekt jego stockowym `vite.config.js`; błąd dowolnego builda kończy proces kodem 1. `dev` przebudowuje paczki przy
  zmianie (odświeżenie strony) i równolegle uruchamia serwer dev Vite projektu.
- **Build w repozytorium paczki:** `public/build/` paczki jest commitowany — serwery instalują paczki z Gita i nie
  potrzebują Node. Po zmianie stylów: `npm run build`, potem commit paczki razem z buildem.
- **Serwowanie:** paczka rejestruje katalog `public/` w providerze:
  `$this->callAfterResolving(PackageAssets::class, fn (PackageAssets $assets) => $assets->add('<paczka>', __DIR__ . '/../public'))`.
  `php artisan polygon:assets` tworzy symlink `public/vendor/<paczka>` (`--copy` kopiuje, gdy hosting nie obsługuje
  symlinków); deploy uruchamia ją sam (`remote.sh`, przed cache). `public/vendor/` projektu jest ignorowany przez git.
- **Widoki:** `@packageAssets('<paczka>', ['resources/scss/app.scss', …])` zamiast `@vite` — znaczniki w kolejności
  wejść, manifest czytany wprost z paczki (`Assets\PackageAssets`), więc testy nie potrzebują symlinku. Brak builda
  albo wejścia to wyjątek z podpowiedzią `npm run build`.

## Tryby kolorystyczne

`Enums\ColorTheme`: `auto` (według systemu: `prefers-color-scheme`, a przy `prefers-contrast: more` duży kontrast),
`light`, `dark`, `wcag` (kontrast co najmniej 4.5:1, WCAG 2.2 AA) i `wcag-contrast` (co najmniej 7:1, AAA).

- Wybór zapisuje skrypt przełącznika strefy w ciasteczku `polygon_theme` (wspólne dla stref, bez szyfrowania —
  `Providers\AssetsServiceProvider` wyłącza je z `EncryptCookies`); serwer czyta je przez `View\ColorThemePreference`
  (nieznana wartość to `auto`) i wstawia w `data-theme` na `<html>`, więc strona nie miga po załadowaniu.
- Każda strefa z widokami ma własne arkusze tokenów: `resources/scss/<strefa>/theme.scss` (jasny i ciemny, `auto`
  przez media query), `<strefa>/wcag/theme.scss` i `<strefa>/wcag-contrast/theme.scss`, ładowane w tej kolejności po
  arkuszu komponentów. Komponenty używają tylko tokenów strefy.
- **Prefiks paczki:** każda paczka z widokami ma własny, krótki prefiks klas i zmiennych CSS (`.<prefiks>-*`,
  `--<prefiks>-*`, znaczniki skryptów `data-<prefiks>-*`), różny od prefiksów innych paczek, więc arkusze stref się
  nie nadpisują; selektory elementów (`body`, `h1`, `a`) tylko w arkuszu strefy, która ładuje go na własnych stronach.
- **Wymagania WCAG** (kontrast tekstu 4.5:1 / 7:1, obramowania kontrolek 3:1, widoczny fokus) obowiązują w motywach
  `wcag` i `wcag-contrast`; `light` i `dark` nie muszą ich w pełni spełniać. W trybach WCAG stanów treści nie
  wyszarza się przezroczystością (obniża kontrast) — tylko elementy nieaktywne.
