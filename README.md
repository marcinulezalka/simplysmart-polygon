# polygon

Rdzeń bazy danych aplikacji simplysmart i narzędzia deweloperskie:

- **Baza danych** — wszystkie migracje i seedery bazowe (także stockowe `users`, `cache`, `jobs`): kolumny standardowe, pule id, pełna historia zmian triggerami z aktorem (żądania `web`, zadania kolejki), modele `DataModel` z relacjami w traitach i etykietami kolumn, przejścia stanu wiersza, konta strefy `polygon_accounts` i konta frontendu `users`, uprawnienia spatie/laravel-permission bez zespołów oraz słowniki lokalizacji (waluty, języki, kraje, stawki VAT, kursy walut, dni wolne, jednostki miar) z seederami, organizacje z typami jednostek, członkostwem kont stref, organizacją hero „Simplysmart” i kontekstem organizacji konta w strefie, odczytami (`Queries\Dictionaries`) i zarządzaniem (`DictionaryManagement`); opis: [docs/baza-danych.md](docs/baza-danych.md); domyślna konfiguracja systemu z plików JSON: [docs/instalacja.md](docs/instalacja.md).
- **Strefy i logowanie** — mechanizm stref jak w admiro (własny segment URL, guard, tabele kont, sesja, role spatie w guardzie strefy) i logowanie kodem z e-maila + PIN z blokadami, limitami, zaufanymi urządzeniami, blokadą ekranu, zmianą PIN-u i zapasowym e-mailem; bez widoków — ekrany i szablony maili dostarcza paczka strefy; opis: [docs/strefy.md](docs/strefy.md).
- **Tłumaczenia** — kaskada tekstów i helpery `text_*` / `local_*` jak w admiro, polskie teksty frameworka (`validation`, `auth`, `passwords`, `pagination`) oraz szablon i import tłumaczeń JSON (`polygon:translations:export`, `polygon:translations:import`); opis: [docs/tlumaczenia.md](docs/tlumaczenia.md).
- **Narzędzia** — wydania SemVer (`vendor/bin/version-release`) i wdrożenia przed-produkcja → produkcja (`vendor/bin/deploy`), instalacja paczek prywatnych spoza repozytorium (`vendor/bin/composer-private`); opis: [docs/deploy.md](docs/deploy.md).

Paczka Composer: `simplysmart/polygon`, namespace `Simplysmart\Polygon\`.

## Wymagania

- PHP ^8.4 z rozszerzeniami `intl` (dane ICU słowników) i `bcmath` (przeliczenia kursów), Laravel 13
- MySQL 8 / MariaDB (triggery historii: uprawnienie `TRIGGER`, przy binlogu `log_bin_trust_function_creators = 1`) albo SQLite
- provider `Simplysmart\Polygon\PolygonServiceProvider` rejestruje się sam (auto-discovery) i dokłada providery obszarów z `src/Providers/` (baza danych, tłumaczenia, konta, uprawnienia); klucze configu paczki są stałymi `PolygonConfig`

## Instalacja w projekcie

Paczka leży lokalnie w `/var/www/packages/simplysmart/polygon`. W `composer.json` projektu:

```json
"repositories": [
    { "name": "simplysmart-polygon", "type": "path", "url": "../packages/simplysmart/polygon", "options": { "symlink": true } }
]
```

```bash
composer require simplysmart/polygon:@dev
php artisan migrate                      # tabele paczki (database/migrations/)
php artisan db:seed                      # seedery bazowe (słowniki, organizacja hero, pliki instalacji) + seedery paczek; migracje nie wstawiają danych
php artisan polygon:organizations:seed   # same typy jednostek i organizacja hero (idempotentnie)
php artisan polygon:install              # same pliki instalacji JSON: strefy, uprawnienia, role, konta startowe
php artisan polygon:account:create jan@example.com "Jan Kowalski" --role=owner   # konto strefy polygon (polygon_accounts)
```

Paczka jest w `require`, bo jej migracje i modele działają też na serwerach. Ustawienia (`config/polygon.php`) zmienia się przez `.env`: `APP_CURRENCY`, `APP_LOCALES`, `APP_COUNTRY`.

Konfiguracje wdrażanych projektów leżą w projekcie: `infrastructure/deploy/vendors/<projekt>/<projekt>.env` albo `infrastructure/deploy/vendors/<projekt>.env` (wzór `resources/deploy/deploy-vendor.env.example`).

## Jakość kodu

```bash
composer install   # raz, zależności paczki
composer test      # PHPUnit
composer lint      # Pint, preset PER + strict_types i nagłówek licencji
composer analyse   # PHPStan, poziom max
```

## Licencja

Simplysmart Restricted Source License 1.0 — © 2026 Marcin Ulezalka.

- [Polski (wiążąca)](LICENSE.md)
- [English (translation)](LICENSE.en.md)

Użytek komercyjny wymaga wpisu w pliku atrybucji projektu — wzór w [THIRD_PARTY.md](THIRD_PARTY.md).
