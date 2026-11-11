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

## Komendy deweloperskie

- `php artisan polygon:icons:subset <katalog> <zestaw:ikona>…` — zapisuje `<katalog>/<zestaw>.json` z podanymi ikonami
  (cały plik zestawu jest zastępowany — podaj wszystkie ikony zestawu, których strefa używa; brakująca ikona przerywa
  komendę). Strefa zwykle ma własną komendę, która zbiera ikony swojego enuma (ulmed: `ulmed:icons:sync`).
- `php artisan polygon:icons:png <zestaw:ikona> <plik.png> [--size=24] [--scale=2] [--color=#1d4873]` — ikona do
  e-maila jako PNG. Klienty poczty nie wyświetlają SVG ani web componentów, dlatego mail osadza PNG
  (`$message->embed()`) w rozmiarze `--size`; plik ma `--scale` razy więcej pikseli (ostry na ekranach o dużej
  gęstości), a kolor jest stały. Rasteryzuje `rsvg-convert` (`apt install librsvg2-bin`, zalecany) albo `inkscape`;
  PNG commituje się w paczce strefy.

## Strefa — wzór (ulmed)

1. Enum ikon interfejsu z wartościami `zestaw:ikona`; `html()` → `IconifyIcon::html()` z klasą wyglądu strefy.
2. Kolekcje `resources/icons/<zestaw>.json` z komendy `polygon:icons:subset` (własny zestaw spoza API — plik JSON
   pisany ręcznie) i licencje zestawów `resources/icons/<zestaw>/LICENSE`.
3. Moduł JS ładowany na każdej stronie: `import { addCollection, appendCustomStyle } from 'iconify-icon'` i od razu
   `addCollection()` każdej kolekcji — nie `window.IconifyPreload`: Vite wykonuje wspólny fragment `iconify-icon` przed
   treścią modułu, więc komponent nie widzi globalu, pobiera ikony z API, a własnego zestawu (spoza API) nie pokazuje;
   `appendCustomStyle()` z `iconify-icon` ustawia styl wnętrza komponentu (Shadow DOM, do którego arkusz strony nie
   sięga), np. `svg{width:100%;height:100%}`, żeby rozmiar dawała klasa ikony.
4. CSS: klasa ikony z `display: inline-block` i wymiarami — miejsce jest zarezerwowane, zanim JS zdefiniuje komponent.
5. Wybór ikony przez użytkownika: pole z nazwą walidowane `Rules\IconName`, zestawy z `IconCatalog::sets()`,
   wyszukiwarka w API Iconify (`/search?query=…&prefixes=…`).
