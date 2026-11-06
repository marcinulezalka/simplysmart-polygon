# Changelog

Format oparty na [Keep a Changelog](https://keepachangelog.com/pl/1.1.0/).

## [Unreleased]

### Added

- Baza danych skopiowana z rdzenia `simplysmart/admiro` (tylko tabele bazowe, bez paczek stref): kolumny standardowe (`Database\Columns`, `ColumnNames`, `CodeColumns`), pule id (`IdSlots`), historia zmian triggerami MySQL/SQLite (`History`), tabele strefy `polygon_*` (`ZoneTables`), `Models\MainModel`/`DataModel`, wyrównanie tabeli `users` do standardu oraz słowniki lokalizacji (`locale_currencies`, `locale_languages`, `locale_countries`, `locale_country_language`, `locale_tax_rates`, `locale_exchange_rates`, `locale_holidays`, `locale_measure_units`) z modelami, enumami i seederami uruchamianymi przez `php artisan polygon:localization:seed`; migracje ładuje `PolygonServiceProvider`, ustawienia w `config/polygon.php`, etykiety enumów w `lang/<locale>/enums.php`; opis w `docs/baza-danych.md`.
- Stockowe migracje Laravela (`create_users_table`, `create_cache_table`, `create_jobs_table`) przeniesione z projektu do paczki oraz tabele uprawnień spatie/laravel-permission bez zespołów (`permissions`, `roles`, `model_has_permissions`, `model_has_roles`, `role_has_permissions`) z kolumnami standardowymi i historią; modele `Models\Permissions\{Role,Permission}`, konfiguracja `Permissions\PermissionDefaults`, zależność `spatie/laravel-permission` ^8.3.
- Model `Models\User` (tabela `users` z kolumnami standardowymi, `HasRoles`, soft delete) z fabryką `Database\Factories\UserFactory`, ustawiany jako model providera `users`, gdy projekt nie ma własnego; seeder główny `Database\Seeders\DatabaseSeeder` (słowniki lokalizacji, bez kont i danych demo) zarejestrowany pod nazwą `DatabaseSeeder` dla `php artisan db:seed`.
- Aktor historii zmian jak w admiro: `Database\HistoryActor`, middleware `Http\Middleware\RecordHistoryActor` na końcu grupy `web` (guard `web`, użytkownik, identyfikator żądania) i `Listeners\SetHistoryActorForJob` dla zadań kolejki.
- Konta strefy `polygon` jak w admiro: model `Models\PolygonAccount` (`polygon_accounts`, `Models\MainAuthenticatable`, e-mail szyfrowany z `email_hash` przez `Contracts\BlindIndex` / `Security\HmacBlindIndex`), guard i provider `polygon` (`Zones\PolygonZone`) oraz komenda `php artisan polygon:account:create {email} {name} [--role=*] [--skip-existing]` (akcja `Auth\CreateAccount`); `users` zostaje dla kont frontendu.
- Specyfikacja bazy jak w admiro: odczyty słowników `Queries\Dictionaries` (`DefaultEntries`, `DictionaryList`, `DictionarySearch`, `TaxRatesInForce`, `ExchangeRatesOn`, `HolidaysIn`, `MeasureUnits`), zmiany `Localization\DictionaryManagement` (z `Dictionary`, `DictionaryChangeRejected`), języki interfejsu `Contracts\InterfaceLocales` / `Localization\DictionaryInterfaceLocales`, przejścia stanu wiersza `Database\RecordStatusTransitions` ze zdarzeniem `Events\RecordStatusChanged` (`Auth\AccountActor`), `Database\LikePattern`, etykiety kolumn `lang/<locale>/data.php` (`PackageText::data()`), `APP_DEFAULT_VAT` (`polygon.localization.vat_percent`); wymaga rozszerzenia `bcmath`.
- Pliki instalacji JSON (domyślna konfiguracja systemu): strefy, uprawnienia, role (`*`, `except`, `inherits`) i konta startowe; plik paczki `database/install/polygon.json` (uprawnienia i role jak strefa administratorów admiro: `guest`, `support`, `owner`, `hero` — root ze wszystkimi uprawnieniami strefy; enumy `Zones\PolygonPermission`, `Zones\PolygonRole`, `Permissions\RequiredRole`, etykiety `lang/<locale>/permissions.php`, `roles.php`; konto `support@simplysmart.pl` z rolą `hero` i losowym id poniżej 1000) oraz pliki projektu z `POLYGON_INSTALL_FILES`; `php artisan polygon:install`, `InstallSeeder` w `DatabaseSeeder`, `IdSlots::random()`, opcjonalne id w `CreateAccount`; opis w `docs/instalacja.md`.
- `HolidaysSeeder` (w `LocalizationSeeder`): ogólnokrajowe dni wolne Polski na lata 2026–2027, jak w bazie skeleton13 (dane Nager.Date), bez nadpisywania istniejących i ręcznych wpisów.
- `ExchangeRatesSeeder` (w `LocalizationSeeder`): kursy NBP (tabela A) CHF, EUR, GBP i USD z 2026-10-29 – 2026-11-05, jak w bazie skeleton13, bez nadpisywania istniejących i ręcznych kursów.
- Tłumaczenia jak w admiro: kaskada `Contracts\TextTranslator` / `Translation\CascadeTextTranslator` (paczka → polygon → aplikacja → klucz, bez języka zapasowego) z helperami `text()`, `text_action()`, `text_data()`, `text_file()`, `text_email()` (`Translation\TextGroup`) i formatowaniem `local_money()`, `local_number()`, `local_date()`, `local_day()`, `local_month()` (`Contracts\LocalFormatter`, `Localization\IntlLocalFormatter`); polskie teksty frameworka `lang/pl/{validation,auth,passwords,pagination}.php` wstawiane przez `Translation\CoreTranslationPath`, akcje `lang/{pl,en}/actions.php`; szablon i import tłumaczeń JSON (`TranslationTemplate`, `TranslationImport`, komendy `polygon:translations:export` i `polygon:translations:import`); `PackageText` korzysta z tej samej kaskady; opis w `docs/tlumaczenia.md`.
- Paczki prywatne jak w admiro: `vendor/bin/composer-private [--dry-run] [--root=]` instaluje paczki z git-ignorowanego `private-packages.json` przez `composer.private.json`, więc ich nazwy nie trafiają do publicznego `composer.json`, `composer.lock` ani dokumentacji projektu.
- Wersja aplikacji z CHANGELOG.md: `View\ApplicationVersion::current()` (najnowsze wydanie projektu, np. do plakietki na stronie startowej) i `Deploy\Release\Changelog::latestRelease()`.
- Stawki VAT seedowane tylko dla kraju aplikacji (`APP_COUNTRY`, domyślnie Polska).
- Licencja po polsku (`LICENSE.md`, wersja wiążąca) z tłumaczeniem angielskim (`LICENSE.en.md`) i linkami między nimi; wzór zgłoszenia użytku komercyjnego w `THIRD_PARTY.md`.
- Narzędzia wydań i wdrożeń: `vendor/bin/version-release` (wydanie SemVer z `[Unreleased]` w CHANGELOG.md, `--dry-run`, `--major-confirm`) i `vendor/bin/deploy <projekt>` (przed-produkcja na komputerze deweloperskim, potem po potwierdzeniu produkcja przez SSH; konfiguracje w `infrastructure/deploy/vendors/` projektu), z testami PHPUnit.

### Changed

- Paczka nie jest już tylko deweloperska: projekty instalują ją w `require` (migracje i modele działają na serwerach); wymaga rozszerzeń `intl` i `bcmath`.

## [0.1.0] - 2026-11-06

### Added

- Szkielet paczki `simplysmart/polygon`: `PolygonServiceProvider` z auto-discovery, Pint (PER, `strict_types`, nagłówek licencji), PHPStan (poziom `max`).
