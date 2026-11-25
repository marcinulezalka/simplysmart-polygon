# Ikony (Iconify)

Ikony stref to ikony [Iconify](https://iconify.design) o nazwie `zestaw:ikona` (np. `lucide:heart-pulse`,
`healthicons:cardiology`), wyświetlane web componentem [`iconify-icon`](https://iconify.design/docs/iconify-icon/)
(npm, zależność projektu). Strefa ma dwa źródła danych ikon:

- **Ikony interfejsu** (przyciski, nawigacja, listy) — lokalne kolekcje w repozytorium paczki strefy: wycinki zestawów
  w formacie Iconify JSON tylko z używanymi ikonami. JS strefy dodaje je komponentowi przez `addCollection()`
  zaraz po imporcie `iconify-icon`, więc ekrany są gotowe od pierwszego renderu i nie pytają API Iconify.
- **Ikony wybrane przez użytkownika** (np. ikona poradni) — dowolna ikona z dozwolonych zestawów; przeglądarka
  pobiera ją z API Iconify, a serwer przy zapisie sprawdza, czy istnieje.

## Polygon

| Klasa | Rola |
|---|---|
| `Icons\IconifyIcon` | Nazwa `zestaw:ikona` (`tryFrom()`, `from()`), `html($class)` — `<iconify-icon icon="…" class="…" aria-hidden="true">` |
| `Icons\IconifyCollection` | Lokalny zestaw Iconify JSON (`load()`, `has()`, `names()`); `svg($name, $attributes)` składa SVG na serwerze — tam, gdzie web component nie działa (PDF) |
| `Icons\IconCatalog` | Dozwolone zestawy (`sets()`, `allows()`), API (`api()`), `exists()` — czy ikona istnieje (cache: istniejąca 30 dni, brakująca godzinę; API nieosiągalne — `null`, bez cache), `subset()` i `svg()` dla komend |
| `Rules\IconName` | Walidacja wyboru użytkownika: format, zestaw z dozwolonych, ikona w API. Gdy API nie odpowiada, przepuszcza poprawną nazwę — zapis nie czeka na zewnętrzną usługę |

Config `polygon.icons`:

| Klucz | `.env` | Domyślnie |
|---|---|---|
| `sets` | `POLYGON_ICON_SETS` | `lucide,fluent,tabler,healthicons` |
| `api` | `POLYGON_ICONIFY_API` | `https://api.iconify.design` |

`Models\Organization::icon()` przyjmuje nazwy z `Organization::ICONS` i dowolne nazwy `zestaw:ikona`.

## Pełne zestawy lokalne (heroicons, tabler, lucide, system-uicons)

Cztery zestawy z `polygon.icons.sets` są serwowane lokalnie, bez zapytań do API Iconify — `src/public/icons/<prefix>.json`
(`Icons\LocalIconSets`; `IconCatalog::exists()`/`svg()` sprawdzają je najpierw, zanim sięgną po API), udostępniane jak
każdy plik `src/public/` (`Providers\AssetsServiceProvider`, `php artisan polygon:assets`) pod
`/vendor/polygon/icons/<prefix>.json` — statycznie, bez PHP. Dane pochodzą z pakietów npm `@iconify-json/<nazwa>`
(`devDependencies` projektu; opisowy `package.json` paczki dokumentuje te same wersje). Przeglądarka (strefa) pobiera
ten sam plik raz i rejestruje go w web komponencie `iconify-icon` (`addCollection`) do renderu i wyszukiwania lokalnego.

Aktualizacja (ręczna, gdy trzeba dociągnąć nowsze ikony — bez trwałej automatyzacji):
1. W projekcie: `npm install` (zaciąga/odświeża `@iconify-json/{heroicons,tabler,lucide,system-uicons}`).
2. Skopiować `node_modules/@iconify-json/<pakiet>/icons.json` do `src/public/icons/<prefix>.json` paczki (prefiks —
   pole `"prefix"` w pliku, nie nazwa pakietu npm; dziś identyczne).
3. `php artisan polygon:assets` (odświeża symlink/kopię `public/vendor/polygon`).
4. Zaktualizować wersje w opisowym `package.json` paczki, żeby nie rozjechały się z tym, co faktycznie skopiowane.

Pojedyncze ikony komponentów polygonu są w `src/resources/icons/` (dziś `line-md:arrow-small-right` przycisku logowania, licencja MIT
obok).

## Komendy deweloperskie

- `php artisan polygon:icons:subset <katalog> <zestaw:ikona>…` — zapisuje `<katalog>/<zestaw>.json` z podanymi ikonami
  (cały plik zestawu jest zastępowany — podaj wszystkie ikony zestawu, których strefa używa; brakująca ikona przerywa
  komendę). Strefa zwykle ma własną komendę, która zbiera ikony swojego enuma (np. `<strefa>:icons:sync`).
- `php artisan polygon:icons:png <zestaw:ikona> <plik.png> [--size=24] [--scale=2] [--color=#1d4873]` — ikona do
  e-maila jako PNG. Klienty poczty nie wyświetlają SVG ani web componentów, dlatego mail osadza PNG
  (`$message->embed()`) w rozmiarze `--size`; plik ma `--scale` razy więcej pikseli (ostry na ekranach o dużej
  gęstości), a kolor jest stały. Rasteryzuje `rsvg-convert` (`apt install librsvg2-bin`, zalecany) albo `inkscape`;
  PNG commituje się w paczce strefy.

## Strefa — wzór

1. Enum ikon interfejsu z wartościami `zestaw:ikona`; `html()` → `IconifyIcon::html()` z klasą wyglądu strefy.
2. Kolekcje `src/resources/icons/<zestaw>.json` z komendy `polygon:icons:subset` (własny zestaw spoza API — plik JSON
   pisany ręcznie) i licencje zestawów `src/resources/icons/<zestaw>/LICENSE`.
3. Moduł JS ładowany na każdej stronie: `import { addCollection, appendCustomStyle } from 'iconify-icon'` i od razu
   `addCollection()` każdej kolekcji — nie `window.IconifyPreload`: Vite wykonuje wspólny fragment `iconify-icon` przed
   treścią modułu, więc komponent nie widzi globalu, pobiera ikony z API, a własnego zestawu (spoza API) nie pokazuje;
   `appendCustomStyle()` z `iconify-icon` ustawia styl wnętrza komponentu (Shadow DOM, do którego arkusz strony nie
   sięga), np. `svg{width:100%;height:100%}`, żeby rozmiar dawała klasa ikony.
4. CSS: klasa ikony z `display: inline-block` i wymiarami — miejsce jest zarezerwowane, zanim JS zdefiniuje komponent.
5. Wybór ikony przez użytkownika: pole z nazwą walidowane `Rules\IconName`, zestawy z `IconCatalog::sets()`,
   wyszukiwarka w API Iconify (`/search?query=…&prefixes=…`).
