# Tłumaczenia i formatowanie

Warstwa tłumaczeń jak w admiro: kaskada tekstów bez języka zapasowego, helpery `text_*` i `local_*`, polskie teksty
frameworka w paczce oraz szablon i import tłumaczeń JSON.

## Kaskada tekstów (`Contracts\TextTranslator`, `Translation\CascadeTextTranslator`)

Klucz `paczka::plik.klucz` jest szukany po kolei: w paczce (z nadpisaniem projektu `lang/vendor/<paczka>/<język>/`),
w polygon (`polygon::`), w `lang/` projektu. Każdy krok pyta tylko o bieżący język; gdy klucza nie ma nigdzie, wraca
klucz w podanej postaci, więc brak tłumaczenia jest od razu widoczny.

| Helper | Klucz | Przykład |
| --- | --- | --- |
| `text($key)` | dowolny | `text('validation.required', ['attribute' => 'email'])` |
| `text_action($key)` | `actions.<klucz>` | `text_action('save')` → „Zapisz” |
| `text_data($model, $kolumna)` | `data.<model>.<kolumna>`, potem `data.<kolumna>` | `text_data(Country::class, 'alpha_code_2')` |
| `text_file($key)` | `files.<klucz>` | etykiety plików eksportu |
| `text_email($key)` | `emails.<klucz>` | treści wiadomości e-mail |

Nowa rodzina tekstów to nowy przypadek `Translation\TextGroup` i jeden helper w `src/helpers.php`. Kod paczki bez
kontenera w konstruktorze (enumy) używa `Localization\PackageText` (ta sama kaskada w przestrzeni `polygon::`).

## Formatowanie (`Contracts\LocalFormatter`, `Localization\IntlLocalFormatter`)

`local_money($kwota, $waluta = null)` (domyślnie `APP_CURRENCY`), `local_number()`, `local_date()`, `local_day()`,
`local_month()` — według bieżącego języka (intl), np. `local_money(1234.5)` → „1 234,50 zł”.

## Teksty frameworka po polsku (`Translation\CoreTranslationPath`)

`lang/pl/{validation,auth,passwords,pagination}.php` paczki są wstawiane między tłumaczenia frameworka a `lang/`
projektu: zastępują angielskie domyślne komunikaty Laravela w języku `pl`, a plik w `lang/pl/` projektu nadal wygrywa
nad paczką. Angielskie komunikaty zostają z frameworka.

## Szablon i import tłumaczeń

```bash
php artisan polygon:translations:export en --source=pl --output=en.json   # teksty paczek + istniejące tłumaczenia
php artisan polygon:translations:export de --source=pl --blank            # pusty szablon nowego języka
php artisan polygon:translations:import en.json                           # do lang/vendor/<przestrzeń>/<język>/
```

Szablon (`Translation\TranslationTemplate`) ma klucze `przestrzeń::plik.klucz` z tekstem źródłowym i tłumaczeniem.
Import (`Translation\TranslationImport`) przyjmuje tylko klucze szablonu z niepustym tłumaczeniem (ścieżki są więc
bezpieczne), zachowuje istniejące nadpisania i zapisuje pliki atomowo; najwyżej 5 MB na plik.

## Współistnienie z admiro

Helpery są definiowane warunkowo (`function_exists`). W projekcie z obiema paczkami wygrywają helpery tej, którą
Composer załaduje pierwszą — taki projekt używa jednej warstwy tłumaczeń (admiro albo polygon).
