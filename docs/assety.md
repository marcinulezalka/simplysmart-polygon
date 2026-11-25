# Assety paczek i tryby kolorystyczne

## Build: Vite w każdej paczce

Jedno `npm run build` / `npm run dev` w projekcie buduje assety wszystkich paczek z `vendor/simplysmart/*` i projektu.
Skrypty projektu wołają orkiestrator `node vendor/simplysmart/polygon/bin/assets.mjs {build|dev}`.

- **Paczka:** w katalogu głównym `vite.config.js` eksportujący zwykły obiekt konfiguracji Vite, bez importu `vite`
  (paczki leżą poza projektem i nie widzą jego `node_modules`), np.
  `export default { build: { rollupOptions: { input: ['resources/scss/app.scss'] } } }`. Konfiguracja paczki w układzie `src/` (docs/strefy.md → „Układ paczki”) ma `root: 'src'`: wejścia
  i klucze manifestu liczą się od `src/`, a build trafia do `src/public/build/`. Źródła w `src/resources/scss/`,
  `src/resources/js/`. Opcjonalny `package.json` (`"private": true`, `"name": "@simplysmart/<paczka>"`)
  tylko jako opis — zależności npm są w projekcie. JS paczki może importować pakiety npm projektu (`import 'iconify-icon'`):
  orkiestrator rozwiązuje gołe importy z plików paczki od katalogu projektu (wtyczka `projectModules`).
- **Tailwind w paczce:** paczka z własnym, niezależnym od jej zwykłego SCSS tematem Tailwind (np. osobny layout)
  dodaje `tailwind: true` do swojego `vite.config.js` (zdjęte przed scaleniem configu, nie trafia do Vite) —
  orkiestrator doda plugin `@tailwindcss/vite` z `node_modules` projektu, tym samym sposobem co ładuje samo Vite.
  Wejście z `@import 'tailwindcss';` i własnym `@theme` idzie obok zwykłych wejść SCSS paczki (inny plik, inny prefiks
  klas/tokenów — patrz „Prefiks paczki” niżej).
- **Orkiestrator:** Vite i preprocesory (np. `sass`) z `node_modules` projektu; każda paczka trafia do swojego
  `public/build/` w katalogu `root` (`src/public/build/`) z `manifest.json` i bazą `/vendor/<paczka>/build/`. `build` buduje paczki po kolei, a na końcu
  projekt jego stockowym `vite.config.js`; błąd dowolnego builda kończy proces kodem 1. `dev` przebudowuje paczki przy
  zmianie (odświeżenie strony) i równolegle uruchamia serwer dev Vite projektu.
- **Build w repozytorium paczki:** `src/public/build/` paczki jest commitowany — serwery instalują paczki z Gita i nie
  potrzebują Node. Po zmianie stylów: `npm run build`, potem commit paczki razem z buildem.
- **Serwowanie:** paczka rejestruje katalog `public/` w providerze:
  `$this->callAfterResolving(PackageAssets::class, fn (PackageAssets $assets) => $assets->add('<paczka>', self::ROOT . '/public'))`.
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
- Każda strefa z widokami ma własne arkusze tokenów: `src/resources/scss/<strefa>/theme.scss` (jasny i ciemny, `auto`
  przez media query), `<strefa>/wcag/theme.scss` i `<strefa>/wcag-contrast/theme.scss`, ładowane w tej kolejności po
  arkuszu komponentów. Komponenty używają tylko tokenów strefy.
- **Prefiks paczki:** każda paczka z widokami ma własny, krótki prefiks klas i zmiennych CSS (`.<prefiks>-*`,
  `--<prefiks>-*`, znaczniki skryptów `data-<prefiks>-*`), różny od prefiksów innych paczek, więc arkusze stref się
  nie nadpisują; selektory elementów (`body`, `h1`, `a`) tylko w arkuszu strefy, która ładuje go na własnych stronach.
- **Wymagania WCAG** (kontrast tekstu 4.5:1 / 7:1, obramowania kontrolek 3:1, widoczny fokus) obowiązują w motywach
  `wcag` i `wcag-contrast`; `light` i `dark` nie muszą ich w pełni spełniać. W trybach WCAG stanów treści nie
  wyszarza się przezroczystością (obniża kontrast) — tylko elementy nieaktywne.

## Motyw Polygon

Strefa `polygon` ma własny motyw: tokeny `--pg-*` w `src/resources/scss/polygon/{theme,wcag/theme,wcag-contrast/theme}.scss`
i komponenty `.pg-*` (przyciski, karty, pola, odznaki, tabele oraz kolory toastów, dzwonka i panelu akcji) w
`polygon/components.scss`. Wejścia: `Theme\ThemeAssets::ENTRIES`; podgląd na żywo: całe Preview.

- Kolory: ciepłe neutralne tła (`bg` — treść, `bar` — górny pasek, `surface` — karty, `sunken` — pola wyróżnione), turkusowy
  akcent `accent`, osobny niebieski `info` podpowiedzi i informacji, `title` nagłówków i `focus` obrysu fokusu.
- Motyw jest opcjonalny. Używa go dokumentacja ([dokumentacja.md](dokumentacja.md)); strefa z własnym motywem go nie ładuje,
  a mechanizmy polygonu (toasty, dzwonek, panel akcji) maluje sama.
- Tokeny każdego trybu są w jednym mixinie o nazwie trybu (`light`, `dark`, `wcag`, `wcag-contrast`), wspólne — w `base`.
  `Theme\ThemeTokens` czyta je z arkuszy, więc nowy token pojawia się w Preview sam. Każdy tryb ma komplet tokenów, a tryby WCAG
  wymagany kontrast — pilnuje tego `tests/Theme/ThemeTokensTest`.
- Nowy komponent: klasa w `components.scss` (tylko tokeny) i strona z przykładem w `preview/`, w swojej grupie
  spisu (podstawy, komponenty, cały motyw); opis wspólny dla wielu stron trafia do „Zasad globalnych” na jego końcu.

## Rozmiar czcionki

`Enums\FontSize`: `a` (standardowy), `aa` (powiększony) i `aaa` (znacznie powiększony) — ten sam mechanizm co tryb
kolorystyczny, niezależny od niego (można łączyć np. `wcag-contrast` z `aaa`).

- Wybór zapisuje skrypt przełącznika strefy w ciasteczku `polygon_font_size` (wspólne dla stref, bez szyfrowania —
  `Providers\AssetsServiceProvider` wyłącza je z `EncryptCookies`); serwer czyta je przez `View\FontSizePreference`
  (nieznana wartość to `a`) i wstawia w `data-font-size` na `<html>`, więc strona nie miga po załadowaniu.
- Każda strefa skaluje swój root font-size w `src/resources/scss/<strefa>/theme.scss`, obok reguł trybu
  kolorystycznego: `:root[data-font-size='aa']` i `:root[data-font-size='aaa']` (rem-y komponentów przeliczają się
  automatycznie — tokeny motywu nie wymagają zmian).

## Bazowe zasady UI stref

Wspólne dla każdej strefy z widokami (tokeny i nazwy — z prefiksem paczki; wzór wartości: `docs/ui.md` paczki strefy):

1. **Zaokrąglenia zagnieżdżone:** kontener z zaokrąglonym elementem blisko krawędzi ma promień zewnętrzny = wewnętrzny
   + padding; skala promieni (elementy w kontenerach, kontrolki, karty) z tokenów motywu.
2. **Cień kart** tylko z tokenu motywu: dwie warstwy (szeroka miękka i bliska, kolory marki albo neutralne motywu w 8%); małe karty w kartach
   mają cień w tych samych kolorach w skali elementu, nie są płaskie; motywy WCAG — bez cienia.
3. **Warstwy tła** (ciepłe neutralne): górny pasek i stopka jaśniejsze od treści, panele boczne płaskie w złamanej
   bieli, treść delikatnie szara, karty białe.
4. **Przyciski:** jeden rozmiar normalny (jak w Bootstrapie i Tablerze: 8 × 16 px, tekst 15 px, ok. 40 px wysokości),
   zawsze z ikoną (przed etykietą; „dalej” po niej), delikatny cień w kolorze przycisku; najechanie i wciśnięcie
   zmieniają tylko kolor, bez przesuwania; pełna szerokość tylko w formularzach logowania.
5. **Czcionki** z licencją OFL hostowane w paczce (woff2, zestawy latin i latin-ext), bez ładowania z Google; kroje
   płatne tylko z licencją.
6. **WCAG** jak wyżej: wymagania kontrastu tylko w motywach `wcag` i `wcag-contrast`, jasny i ciemny zachowują swój
   wygląd.
