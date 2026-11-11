# Changelog

Format oparty na [Keep a Changelog](https://keepachangelog.com/pl/1.1.0/).

## [Unreleased]

### Added

- Faktury i integracja z Fakturownią (`docs/baza-danych.md` §21): tabele `invoicing_accounts` (konto organizacji głównej, szyfrowany token API), `invoicing_contractor_links`, `invoices` i `invoice_items`; klient API Fakturowni z ponawianiem odczytów i tokenem usuwanym z komunikatów błędów; dwukierunkowa synchronizacja kontrahentów z klientami (dopasowanie po NIP-ie, wygrywa nowsza zmiana, bez pętli zwrotnej) — po zapisie kontrahenta z kolejki i co godzinę (`polygon:invoicing:contractors-sync`); wystawienie faktury z migawką stron i kwotami VAT liczonymi dziesiętnie, wysyłka z kolejki bez duplikatów po ponowieniu (`oid`), PDF przez serwer, ponowienie (`polygon:invoicing:invoices-send`). Wymaga migracji, workera kolejki i harmonogramu.
- Ikony Iconify (`docs/ikony.md`): nazwa `zestaw:ikona` i znacznik web componentu `iconify-icon` (`Icons\IconifyIcon`), lokalne kolekcje Iconify JSON składane w SVG na serwerze dla PDF (`Icons\IconifyCollection`), dozwolone zestawy (`polygon.icons.sets`, `POLYGON_ICON_SETS`, domyślnie lucide, fluent, tabler, healthicons) i sprawdzanie ikony w API Iconify z cache (`Icons\IconCatalog`, `POLYGON_ICONIFY_API`), reguła `Rules\IconName` (teksty `icons.*`), komendy deweloperskie `polygon:icons:subset` (wycinek zestawu do lokalnego JSON) i `polygon:icons:png` (ikona do e-maila jako PNG przez rsvg-convert albo inkscape). `Organization::icon()` przyjmuje też dowolną nazwę `zestaw:ikona`.
- Orkiestrator assetów: JS paczki może importować pakiety npm projektu (gołe importy rozwiązywane od katalogu projektu).
- Komenda `php artisan polygon:calendar:overflow-repair` (`Calendar\OverflowQueueRepair`): jednorazowo po wdrożeniu nadaje kolejne godziny rezerwacjom nadliczbowym od dziś zapisanym jeszcze na samym końcu bloku; rezerwacji już w kolejce nie rusza, uruchomiona ponownie niczego nie zmienia.
- `composer check` w paczce: lint, PHPStan i testy w jednym przebiegu; projekt uruchamia go przy `composer deploy` (`check:packages`). Przed commitem wystarczą testy obejmujące zmianę (`docs/deploy.md`).
- Reset PIN-u przez właściciela konta (`Auth\ResetOwnPin`, trasa `<strefa>.account.pin.reset`): po obecnym PIN-ie (liczonym do blokady, wspólny limit ze zmianą PIN-u) PIN zostaje usunięty, wszystkie sesje i zaufane urządzenia unieważnione (`AccessRevocationReason::PinResetByOwner`), a konto wylogowane z komunikatem i potwierdzeniem e-mailem (`Mail\PinResetMail`, szablon strefy `pin-reset`, teksty `emails.pin_reset.*`); nowy PIN ustawia przy następnym logowaniu kodem. Teksty opisu procesu `account.pin_reset_*`.
- Domyślna organizacja konta po zalogowaniu: zapisana per osoba w `json_default_settings.organization` konta strefy (`Organizations\DefaultOrganization`). Osoba ustawia ją sama (trasa `<strefa>.account.organization.default`, `OrganizationContext::$default` dla przełącznika) albo plik instalacji (`accounts[].defaults.organization`, tylko gdy konto nie ma jeszcze własnego wyboru). Organizacja, która przestała być kontekstem konta, jest pomijana — wtedy jak dotąd najstarsze członkostwo.

### Changed

- Rezerwacja nadliczbowa (`BookSlot::bookOverflow()`) ma własną godzinę zapisaną w bazie: pierwsze wolne miejsce co `slot_minutes` od końca bloku (`FreeSlots::nextOverflow()`, `ResourceSchedule::nextOverflowAt()`; miejsce odwołanej wraca do kolejki), na jeden slot, zamiast zerowej długości na końcu bloku. Z blokiem wiąże ją `json_attributes.overflow_after` (`ResourceBooking::OVERFLOW_AFTER`, `ResourceSchedule::holds()`); slotów dalej nie zajmuje. `FreeSlots::roomTakenAfter()` sprawdza gabinet w slocie kolejnej rezerwacji nadliczbowej.
- Wolne terminy dnia dzisiejszego (`Calendar\FreeSlots`): cały grafik dnia, także sloty, których godzina już minęła — nie znikają w trakcie dnia i można na nie zapisać (`BookSlot::book()`); zapis nadliczbowy (`bookOverflow()`) dziś także po końcu bloku. Terminów nie ma tylko w dniach wcześniejszych niż dziś.

### Fixed

- Grafiku z przyszłymi rezerwacjami nadliczbowymi nie można już usunąć (`SaveSchedule::delete()`) — wcześniej nie były liczone, bo stały na samym końcu bloku.
- Strefa czasowa aplikacji z `APP_TIMEZONE` (`Localization\ApplicationTimezone`, `PolygonConfig::TIMEZONE`): stockowy `config/app.php` Laravela ma na sztywno UTC, więc `now()` i `today()` liczyły czas UTC, a godziny grafików są lokalne. Pusta albo nieznana wartość zostawia strefę frameworka. Znaczniki czasu zapisane wcześniej w UTC (`created_at` itp.) są teraz czytane w strefie aplikacji.

## [0.12.0] - 2026-11-10

### Added

- Przyjmujący bloku grafiku i rezerwacji: `ResourceSchedule::performerAccount()` / `performerName()` i `ResourceBooking::performerAccount()` / `performerName()` (wspólna logika w `Calendar\Performer`) — konto strefy wykonawcy albo null i jego nazwa do wyświetlenia albo pusty tekst, bez sprawdzania typu relacji polimorficznej `performer` w kodzie stref; korzystają z nich `Calendar\FreeSlots` i `Leases\Wallboard`.
- Data zawarcia umowy najmu: kolumna `resource_leases.signed_on` (`ResourceLease::SIGNED_ON`, migracja z synchronizacją triggerów historii, `LeaseInput::$signedOn`, pole `signed` umowy w plikach instalacji) — tylko informacja o podpisaniu; okres i cykl tygodni okien liczą się nadal od `valid_from` (rozpoczęcie cyklu).
- Rezerwacja nadliczbowa w kalendarzu (`Calendar\BookSlot::bookOverflow()`, `FreeSlots::scheduleEndingAt()`): ponad wolne sloty, na końcu bloku grafiku, o zerowej długości (nie zajmuje slotów), oznaczona `json_attributes.overflow` (`ResourceBooking::OVERFLOW`, `isOverflow()`); czy wykonawca przyjmuje nadliczbowo, decyduje paczka strefy. `FreeSlots::roomTakenAfter()` sprawdza dostępność zasobu przez slot po końcu bloku (`Calendar\RoomTaken`, `Enums\RoomTakenBy`: inny grafik, najem na wyłączność innej jednostki, rezerwacja innego wykonawcy).
- Stan rezerwacji „wykonana” (`BookingStatus::Completed`): zajmuje termin, osiągalny z zapisanej i potwierdzonej, końcowy (`Calendar\BookingStatusChange`).
- Czas potwierdzenia rezerwacji: kolumna `resource_bookings.confirmed_at` (`ResourceBooking::CONFIRMED_AT`, migracja z synchronizacją triggerów historii), ustawiana przez `Calendar\BookingStatusChange` przy potwierdzeniu obok `confirmed_by`.
- Polecenie po wdrożeniu w konfiguracji projektu: `DEPLOY_RELOAD` (serwer) i `PREPRODUCTION_RELOAD` (przed-produkcja), np. przeładowanie PHP-FPM, żeby opcache nie serwował starego kodu i starego cache tras (`docs/deploy.md`, „Cache i opcache”).
- Assety paczek budowane Vite (`docs/assety.md`): orkiestrator `bin/assets.mjs {build|dev}` (skrypty `npm run build`/`dev` projektu) buduje każdą paczkę z `vite.config.js` do jej `public/build/` Vite i preprocesorami z projektu; `Assets\PackageAssets` (rejestr paczek, dyrektywa `@packageAssets`), `php artisan polygon:assets` (symlink albo `--copy` do `public/vendor/<paczka>`), krok `polygon:assets` w deployu.
- Tryby kolorystyczne `Enums\ColorTheme` (automatyczny, jasny, ciemny, WCAG, WCAG duży kontrast) i `View\ColorThemePreference` (ciasteczko `polygon_theme` bez szyfrowania) dla `data-theme` na `<html>`.
- Strona startowa konta po zalogowaniu (klucz `home` configu strefy, `Contracts\ZoneHome`): strefa wybiera trasę dla konta (np. pulpit roli) w zespole spatie bieżącej organizacji; bez niej, bez wyboru albo gdy trasy nie ma — jak dotąd `<strefa>.dashboard`.

### Changed

- `Zones\ZonePermission::middleware()` przyjmuje kilka uprawnień — trasa otwiera się przy dowolnym z nich (`zone.permission:a,b`).
- Deploy (`remote.sh`) czyści cache konfiguracji, tras, widoków i zdarzeń zaraz po instalacji paczek (wcześniej dopiero na końcu), a na końcu restartuje workery kolejki (`queue:restart`).
- Hash sesji w adresie (`url_key` strefy) stoi teraz po slugu strefy: `/<slug>/<hash>/…` zamiast `/<hash>/<slug>/…` — wszystkie adresy strefy zaczynają się od jej sluga.

### Fixed

- Strona startowa konta (`home` strefy, np. pulpit roli) zamiast ogólnego pulpitu: dla zalogowanego na trasie tylko dla gości (`ZoneRedirects::userRedirect()`), na wejściu do strefy (nowe `ZoneRedirects::entry()`) i po zalogowaniu, gdy zamierzoną stroną był ogólny pulpit (`ZoneRedirects::isDashboard()` w `CompleteSignIn`).
- Hash sesji w adresie (`url_key`): po ponownym zalogowaniu zamierzona strona zapamiętana z hashem poprzedniej sesji (po wylogowaniu albo wygaśnięciu sesji) dawała 404 — teraz dostaje hash nowej sesji (`Zones\ZoneUrlKey::rewrite()` w `Auth\SignIn\CompleteSignIn`).

## [0.11.0] - 2026-11-07

### Added

- Hash sesji w adresie (`url_key` strefy, `Zones\ZoneUrlKey`): ekrany po zalogowaniu pod `/<hash>/<slug>/…`, losowy hash dla sesji (nowy po wylogowaniu), `route()` dokłada go sam (`Http\Middleware\ApplyZoneUrlKey`), a brak albo zły hash to 404 przed logowaniem (`RequireZoneUrlKey`) — utrudnia robotom i linkom spoza sesji; logowanie i trasy publiczne bez zmian, mail o nowym urządzeniu linkuje wtedy do logowania. Domyślnie wyłączony.

## [0.10.0] - 2026-11-07

### Added

- `FreeSlots::on()` i `days()` z opcjonalnym zasobem (terminy tylko na nim, np. na wynajętym gabinecie).

### Changed

- Reguła „rekord nachodzi na okres ważności” w jednym miejscu (`Calendar\ValidityPeriod`) dla kolizji grafiku, najmu i odczytu najmów.

## [0.9.0] - 2026-11-07

### Added

- Najem na żądanie (`Enums\LeaseMode::OnDemand`, `resource_leases.mode`): najemca korzysta z wolnego czasu zasobu w oknach umowy bez wyłączności; grafik wypełniający wolny czas (`resource_schedules.fills_gaps`, `ScheduleInput::$fillsGaps`) nie koliduje z grafikiem zasobu, a wolne terminy omijają inny grafik, okna najmów na wyłączność i rezerwacje; `ResourceLeases::bookingCounts()` — wizyty umowy w okresie do rozliczenia; tablica pokazuje przy nim tylko zapisane wizyty. W plikach instalacji `mode` umowy i `fills_gaps` grafiku.

## [0.8.0] - 2026-11-07

### Added

- Cykl tygodni w kalendarzu (`Calendar\WeekCycle`): grafik (`resource_schedules.week_interval`, `ScheduleInput::$weekInterval`) i okna umowy najmu (`resource_leases.week_interval`, `LeaseInput::$weekInterval`) co 1–8 tygodni, licząc od tygodnia `valid_from`; kolizje tylko przy wspólnych tygodniach, grafik najemcy w cyklu umowy, wolne terminy i tablica tylko w tygodniach cyklu. W plikach instalacji `every_weeks` grafiku i umowy.

## [0.7.0] - 2026-11-07

### Added

- Pliki instalacji: `attributes` organizacji i konta strefy — dowolne atrybuty (np. identyfikatory rejestrowe placówki, numery zawodowe lekarza) zapisywane w `json_attributes` rekordu (`Install\InstallAttributes`), także w istniejącej organizacji i koncie (klucz z pliku zastępuje ten sam klucz, pozostałe zostają; zapis tylko przy zmianie).

## [0.6.0] - 2026-11-07

### Added

- Najem zasobów i kooperacja (docs/baza-danych.md §20): `resource_leases` (`Models\ResourceLease`) — zasób właściciela wynajęty organizacji najemcy z jej podorganizacją kooperacji (`organizations.is_cooperation`), okresem, oknami godzinowymi (`Leases\LeaseWindows`, kilka okien w dniu), poziomami udostępnienia (`Enums\LeaseAccess`: zapis wizyt, podgląd, publiczna tablica) i tokenem tablicy; `Leases\SaveLease` (kolizje `LeaseConflict`, wypowiedzenie skracające grafik najemcy, nowy token tablicy), `Queries\Leases\ResourceLeases` (umowy właściciela, kanały kooperacji), `Leases\Wallboard` (godziny przyjęć bez danych osób). Kalendarz pilnuje najmu: grafik właściciela nie wchodzi w wynajęte okna (`ScheduleConflict::LeasedOut`), grafik najemcy mieści się w umowie (`OutsideLease`), a po końcu umowy nie ma wolnych terminów. Umowy startowe w plikach instalacji (`organizations.<klucz>.leases`, `is_cooperation`).

## [0.5.0] - 2026-11-07

### Added

- Kontrahenci organizacji (docs/baza-danych.md §19): `contractors` z danymi firmy i NIP-em unikalnym w organizacji, osoby kontaktowe (`contractor_contacts`, jeden kontakt główny) i umowy (`contractor_contracts`, `Enums\ContractStatus`); `Contractors\SaveContractor` (normalizacja i sprawdzenie NIP/REGON), `SaveContact`, `SaveContract`, `Queries\Contractors\ContractorSearch` (organizacja i jej przodkowie, nazwa albo początek NIP-u). Polskie identyfikatory `Identifiers\Nip`, `Regon`, `Pesel` z regułami walidacji `Rules\Nip`, `Rules\Regon`, `Rules\Pesel`. `OrganizationUnits::lineage()` — organizacja i jej przodkowie (wspólne dla zasobów i kontrahentów).

## [0.4.0] - 2026-11-07

### Added

- Kalendarz zasobów (docs/baza-danych.md §18): grafik zasobu (`resource_schedules`, `Models\ResourceSchedule`) z wykonawcą — kontem dowolnej strefy — i slotami oraz rezerwacje (`resource_bookings`, `Models\ResourceBooking`, `Enums\BookingStatus`, rodzaj z paczki strefy); `Calendar\SaveSchedule` (kolizje zasobu i wykonawcy, blokada), `FreeSlots` (bez minionych, zajętych i dni wolnych), `BookSlot` (blokada zasobu i wykonawcy przed podwójną rezerwacją), `BookingStatusChange`, `DayBookings`; `Queries\Organizations\OrganizationUnits` (organizacja i jej jednostki). Grafik startowy w plikach instalacji (`organizations.<klucz>.schedule`). Przeniesione z paczki strefy jako mechanizm ogólny.

## [0.3.0] - 2026-11-07

### Added

- Zasoby organizacji (`organization_resources`, docs/baza-danych.md §17): gabinety, sprzęt i inne zasoby organizacji z rodzajem (`Enums\ResourceKind`) i kodem unikalnym w organizacji, model `Models\OrganizationResource`, relacja `Organization::resources()`, odczyt `Queries\Organizations\OrganizationResources` (zasoby organizacji i jej przodków) oraz zasoby startowe w plikach instalacji (`organizations.<klucz>.resources`, zakładane tylko brakujące).
- `Permissions\OrganizationRoles::holders()`: aktywne konta modelu strefy z rolą w danej organizacji (np. lekarze poradni), bez ról globalnych i ról w innych organizacjach.
- `vendor/bin/deploy --fresh`: baza od nowa we wdrażanych środowiskach — `migrate:fresh` (usuwa wszystkie dane) i `db:seed` z plikami instalacji zamiast migracji i `polygon:install` (`remote.sh` z `DEPLOY_FRESH=1`); osobne potwierdzenie dla przed-produkcji i produkcji (także z `--production`, bez terminala odmowa), `--dry-run` opisuje plan.

## [0.2.0] - 2026-11-07

### Added

- Baza danych polygon (tylko tabele bazowe, bez paczek stref): kolumny standardowe (`Database\Columns`, `ColumnNames`, `CodeColumns`), pule id (`IdSlots`), historia zmian triggerami MySQL/SQLite (`History`), tabele strefy `polygon_*` (`ZoneTables`), `Models\MainModel`/`DataModel`, wyrównanie tabeli `users` do standardu oraz słowniki lokalizacji (`locale_currencies`, `locale_languages`, `locale_countries`, `locale_country_language`, `locale_tax_rates`, `locale_exchange_rates`, `locale_holidays`, `locale_measure_units`) z modelami, enumami i seederami uruchamianymi przez `php artisan polygon:localization:seed`; migracje ładuje `PolygonServiceProvider`, ustawienia w `config/polygon.php`, etykiety enumów w `lang/<locale>/enums.php`; opis w `docs/baza-danych.md`.
- Stockowe migracje Laravela (`create_users_table`, `create_cache_table`, `create_jobs_table`) przeniesione z projektu do paczki oraz tabele uprawnień spatie/laravel-permission bez zespołów (`permissions`, `roles`, `model_has_permissions`, `model_has_roles`, `role_has_permissions`) z kolumnami standardowymi i historią; modele `Models\Permissions\{Role,Permission}`, konfiguracja `Permissions\PermissionDefaults`, zależność `spatie/laravel-permission` ^8.3.
- Model `Models\User` (tabela `users` z kolumnami standardowymi, `HasRoles`, soft delete) z fabryką `Database\Factories\UserFactory`, ustawiany jako model providera `users`, gdy projekt nie ma własnego; seeder główny `Database\Seeders\DatabaseSeeder` (słowniki lokalizacji, bez kont i danych demo) zarejestrowany pod nazwą `DatabaseSeeder` dla `php artisan db:seed`.
- Aktor historii zmian: `Database\HistoryActor`, middleware `Http\Middleware\RecordHistoryActor` na końcu grupy `web` (guard `web`, użytkownik, identyfikator żądania) i `Listeners\SetHistoryActorForJob` dla zadań kolejki.
- Konta strefy `polygon`: model `Models\PolygonAccount` (`polygon_accounts`, `Models\MainAuthenticatable`, e-mail szyfrowany z `email_hash` przez `Contracts\BlindIndex` / `Security\HmacBlindIndex`), guard i provider `polygon` (`Zones\PolygonZone`) oraz komenda `php artisan polygon:account:create {email} {name} [--role=*] [--skip-existing]` (akcja `Auth\CreateAccount`); `users` zostaje dla kont frontendu.
- Specyfikacja bazy: odczyty słowników `Queries\Dictionaries` (`DefaultEntries`, `DictionaryList`, `DictionarySearch`, `TaxRatesInForce`, `ExchangeRatesOn`, `HolidaysIn`, `MeasureUnits`), zmiany `Localization\DictionaryManagement` (z `Dictionary`, `DictionaryChangeRejected`), języki interfejsu `Contracts\InterfaceLocales` / `Localization\DictionaryInterfaceLocales`, przejścia stanu wiersza `Database\RecordStatusTransitions` ze zdarzeniem `Events\RecordStatusChanged` (`Auth\AccountActor`), `Database\LikePattern`, etykiety kolumn `lang/<locale>/data.php` (`PackageText::data()`), `APP_DEFAULT_VAT` (`polygon.localization.vat_percent`); wymaga rozszerzenia `bcmath`.
- Pliki instalacji JSON (domyślna konfiguracja systemu): strefy, uprawnienia, role (`*`, `except`, `inherits`) i konta startowe; plik paczki `database/install/polygon.json` (uprawnienia i role strefy administratorów: `guest`, `support`, `owner`, `hero` — root ze wszystkimi uprawnieniami strefy; enumy `Zones\PolygonPermission`, `Permissions\RequiredRole`, etykiety `lang/<locale>/permissions.php`, `roles.php`; konto `support@simplysmart.pl` z rolą `hero` i losowym id poniżej 1000) oraz pliki projektu z `POLYGON_INSTALL_FILES`; `php artisan polygon:install`, `InstallSeeder` w `DatabaseSeeder`, `IdSlots::random()`, opcjonalne id w `CreateAccount`; opis w `docs/instalacja.md`.
- `HolidaysSeeder` (w `LocalizationSeeder`): ogólnokrajowe dni wolne Polski na lata 2026–2027, jak w bazie skeleton13 (dane Nager.Date), bez nadpisywania istniejących i ręcznych wpisów.
- `ExchangeRatesSeeder` (w `LocalizationSeeder`): kursy NBP (tabela A) CHF, EUR, GBP i USD z 2026-10-29 – 2026-11-05, jak w bazie skeleton13, bez nadpisywania istniejących i ręcznych kursów.
- Tłumaczenia: kaskada `Contracts\TextTranslator` / `Translation\CascadeTextTranslator` (paczka → polygon → aplikacja → klucz, bez języka zapasowego) z helperami `text()`, `text_action()`, `text_data()`, `text_file()`, `text_email()` (`Translation\TextGroup`) i formatowaniem `local_money()`, `local_number()`, `local_date()`, `local_day()`, `local_month()` (`Contracts\LocalFormatter`, `Localization\IntlLocalFormatter`); polskie teksty frameworka `lang/pl/{validation,auth,passwords,pagination}.php` wstawiane przez `Translation\CoreTranslationPath`, akcje `lang/{pl,en}/actions.php`; szablon i import tłumaczeń JSON (`TranslationTemplate`, `TranslationImport`, komendy `polygon:translations:export` i `polygon:translations:import`); `PackageText` korzysta z tej samej kaskady; opis w `docs/tlumaczenia.md`.
- Paczki prywatne: `vendor/bin/composer-private [--dry-run] [--root=]` instaluje paczki z git-ignorowanego `private-packages.json` przez `composer.private.json`, więc ich nazwy nie trafiają do publicznego `composer.json`, `composer.lock` ani dokumentacji projektu.
- Wersja aplikacji z CHANGELOG.md: `View\ApplicationVersion::current()` (najnowsze wydanie projektu, np. do plakietki na stronie startowej) i `Deploy\Release\Changelog::latestRelease()`.
- Stawki VAT seedowane tylko dla kraju aplikacji (`APP_COUNTRY`, domyślnie Polska).
- Licencja po polsku (`LICENSE.md`, wersja wiążąca) z tłumaczeniem angielskim (`LICENSE.en.md`) i linkami między nimi; wzór zgłoszenia użytku komercyjnego w `THIRD_PARTY.md`.
- Narzędzia wydań i wdrożeń: `vendor/bin/version-release` (wydanie SemVer z `[Unreleased]` w CHANGELOG.md, `--dry-run`, `--major-confirm`) i `vendor/bin/deploy <projekt>` (przed-produkcja na komputerze deweloperskim, potem po potwierdzeniu produkcja przez SSH; konfiguracje w `infrastructure/deploy/vendors/` projektu), z testami PHPUnit.
- Strefy (bez kontekstu w adresie i wyglądu stref): baza providera `Zones\ZoneServiceProvider`, definicja `ZoneDefinition` z configu (`name`, `slug`, `model`, `session`, `auth`, `texts`, `views`, `mail.views`), rejestr `Contracts\ZoneRegistry`, strefa i konto żądania (`CurrentZone`, `CurrentAccount`), guard i provider dopisywane do config/auth, grupa middleware `zone.<strefa>` z osobną sesją strefy, pliki tras `public.php` / `account.php` / `zone.php` (`AccessTier`), uprawnienia tras `ZonePermission::middleware()` (alias `zone.permission`) i baza modelu kont `Models\ZoneAccount`; opis w `docs/strefy.md`.
- Logowanie stref kodem z e-maila i PIN-em: ustawienie PIN-u przy pierwszym logowaniu, limity i blokady, zaufane urządzenia, wylogowanie (także ze wszystkich urządzeń), blokada ekranu, zmiana PIN-u, zapasowy e-mail i reset PIN-u, maile bezpieczeństwa (`Mail\ZoneMailable` z szablonami paczki strefy), config `polygon.auth`, `ZONE_SESSION_LIFETIME`, teksty `lang/{pl,en}/{sign_in,account,emails}.php` i komenda `polygon:auth:prune` (co godzinę). polygon nie ma widoków: ekrany (`Zones\ZoneView`) i maile podaje paczka strefy.
- Komenda konta dla każdej strefy: `Console\CreateZoneAccountCommand` (`<strefa>:account:create`) i akcja `Auth\CreateZoneAccount`; pliki instalacji paczek stref przez `Install\InstallFiles::add()`.
- Katalog ról każdej strefy w plikach instalacji (sekcja główna `roles`, uprawnienia `[]` albo `"*"`, strefa nadaje własne): w pliku paczki `guest`, `support`, `owner`, `hero` oraz `registration`, `doctor`, `accountant`, `analyst`, `manager` (enum `Permissions\BaseRole`, etykiety `polygon::roles.<rola>`); dostaje go każda zarejestrowana strefa z kontami.
- Organizacje: tabele `organizations` (drzewo, departamenty, nazwa skrócona, kolor, dane firmy z krajem), `organization_unit_types` (12 typów domyślnych z polami podorganizacji) i `organization_members` (członkostwo kont stref, niezależne od ról), z historią; modele `Models\{Organization,OrganizationUnitType,OrganizationMember}`, relacje w traitach (`ZoneAccount::organizations()`), etykiety kolumn pl/en, akcja `Organizations\OrganizationMembership`, odczyty `Queries\Organizations\{ActiveMemberships,ActiveOrganizations}`, seedery `OrganizationsSeeder` (`php artisan polygon:organizations:seed`, także w `db:seed`); opis w `docs/baza-danych.md` §16.
- Organizacja hero „Simplysmart” (`Organization::HERO_ID` = 1, `HeroOrganizationSeeder` w `db:seed`): konta z rolą `hero` (komenda konta, pliki instalacji) zostają jej członkami, jej członek może pracować w kontekście każdej aktywnej organizacji, a frontend stref jej nie pokazuje (scope `visible()`).
- Kontekst organizacji konta zalogowanego w strefie: `Contracts\CurrentOrganization` (wybór w sesji strefy, domyślnie organizacja hero dla hero albo najstarsze członkostwo; `visible()`, `contexts()` bez organizacji hero, `choose()`), trasa `<strefa>.account.organization.switch` (`POST account/organization`, pole `organization` = token) i teksty `account.organization.*`; opis w `docs/strefy.md`.
- Manifest wdrożeniowy: `vendor/bin/composer-private --deploy [--dry-run]` buduje `composer.deploy.json` i `composer.deploy.lock` (paczki z repozytoriów `path`, także prywatne, z ich repozytoriów git `origin`; `Deploy\PrivatePackages\DeployLock`, `DeployManifest`), a `vendor/bin/deploy <projekt>` tworzy go na nowo przy każdym wdrożeniu, kopiuje do przed-produkcji, wysyła na serwer (`scp`) i instaluje z niego paczki (`DEPLOY_COMPOSER_MANIFEST` w `remote.sh`); projekt bez paczek `path` instaluje dalej z `composer.lock`.
- Plik instalacji projektu przy wdrożeniu: `vendor/bin/deploy <projekt>` kopiuje `infrastructure/deploy/vendors/<projekt>.json` (konta, role, uprawnienia; poza repozytorium) do przed-produkcji i na serwer jako `storage/app/<projekt>.install.json`, a `remote.sh` po migracjach uruchamia przy każdym wdrożeniu `php artisan polygon:organizations:seed` (typy jednostek, organizacja hero) i `php artisan polygon:install` (pliki paczek, `POLYGON_INSTALL_FILES` i ten plik).
- Organizacje w plikach instalacji: sekcja główna `organizations` (klucz z pliku w `json_attributes.install_key`, nazwa, nazwa skrócona, organizacja nadrzędna, typ jednostki po kodzie, departament, dane firmy) i `accounts[].organizations` — konto zostaje członkiem (personelem) organizacji; instalacja zakłada tylko brakujące organizacje (rodzic przed dzieckiem, bez cykli) i tylko dopisuje członkostwa (`Install\InstallOrganization`, `InstallOrganizations`).
- Organizacja hero jako root w plikach instalacji: `"parent": "simplysmart"` (`Organization::HERO_KEY`) wpina organizację pod niewidoczną we frontendzie organizację hero; klucz jest zarezerwowany (nie do zdefiniowania ani przypisania kontu).
- Typ jednostki „Siedziba” (`siedziba`, prefiks `SIE-`, pierwszy na liście) w `OrganizationUnitTypeSeeder`; istniejące bazy dostają go przy najbliższym `db:seed` (`polygon:organizations:seed`).
- Seedery paczek: rejestr `Database\PackageSeeders` — paczka dopisuje swój seeder w providerze, a `db:seed` uruchamia go po seederach bazowych polygon (`DatabaseSeeder::BASE`).
- Klucz komunikatu po przekierowaniu `Http\StatusMessage::KEY` dla kontrolerów i widoków paczek stref; wspólna baza komend `polygon:<obszar>:seed` (`Console\SeedCommand`).
- Konfiguracja wdrożenia w katalogu projektu: `vendor/bin/deploy <projekt>` czyta `infrastructure/deploy/vendors/<projekt>/<projekt>.env` (i plik instalacji `<projekt>.json` z tego katalogu), a gdy go nie ma — dotychczasowy układ płaski `infrastructure/deploy/vendors/<projekt>.env`; lista dostępnych projektów obejmuje oba układy.
- `vendor/bin/deploy --with-push` wypycha też paczki z repozytoriów path wymagane przez projekt (przed projektem, `git push --set-upstream origin HEAD`), a `--dry-run --with-push` je wymienia; niewypchnięte commity paczki są wykrywane także bez gałęzi śledzonej (commity nieobecne w żadnej gałęzi `origin`).
- Role konta per organizacja: zespoły spatie/laravel-permission, w których zespół to organizacja — migracja `add_organization_to_permission_tables` (`organization_id` w `roles`, `model_has_roles`, `model_has_permissions`; dotychczasowe przypisania zostają globalne), `Permissions\OrganizationRoles` (nadawanie i odczyt ról w organizacji) i `Http\Middleware\UseOrganizationPermissions` w grupie strefy (role i uprawnienia liczą się w bieżącej organizacji; hero i konta bez organizacji — role globalne). W plikach instalacji `accounts[].organizations` może być obiektem `{"<organizacja>": ["<rola>"]}` (np. właściciel w jednej organizacji, lekarz w drugiej), a `roles` konta z organizacjami trafiają do każdej z nich.
- Marka simplysmart w `src/Brand/`: logotyp (SVG, czarny) z wersjami PNG na jasne i ciemne tło (800/1600/2400 px) i sygnet „S” 1024×1024 na jasne i ciemne tło; zasady użycia w `src/Brand/README.md`.
- `docs/deploy.md`: środowisko serwera — wzorcem jest `infrastructure/deploy/vendors/<projekt>/<projekt>.env.production` przenoszony ręcznie na serwer jako `.env.production` (deploy go nie wysyła) — oraz kolejka i worker: maile logowania i bezpieczeństwa idą przez kolejkę, z `QUEUE_CONNECTION=database` potrzebny jest worker z crona (deploy go nie uruchamia), z przykładem i diagnozą.
- `vendor/bin/deploy`: niewypchnięte commity projektu są wykrywane jak w paczkach — commity nieobecne w żadnej gałęzi `origin`, także bez gałęzi śledzonej — a `--with-push` wypycha projekt przez `git push --set-upstream origin HEAD`; plan (`--dry-run`) przypomina o ręcznym przeniesieniu `.env.production` i o workerze kolejki.

### Changed

- Instalator podzielony według odpowiedzialności: `Install\Installer` tylko koordynuje, organizacje zapisuje `Install\OrganizationInstaller` (stała `INSTALL_KEY` przeniesiona z `Installer`), strefy z uprawnieniami, rolami i kontami — `Install\ZoneInstaller`; sekcję organizacji pliku czyta `Install\InstallOrganizationParser`, pojedyncze wartości `Install\InstallValues` (wzorce `PERMISSION_PATTERN`, `ROLE_PATTERN`); błędy połączonych plików przez `InvalidInstallFile::merged()`.
- Role w organizacji z pliku instalacji nadaje `OrganizationRoles::assignIn()`: organizacja bez całkowitego id to `LogicException`, a nie ciche nadanie ról globalnych.
- `Http\Middleware\UseOrganizationPermissions` przełącza zespół spatie przez publiczne `OrganizationRoles::within()` (jedno miejsce przełączania zespołu i odświeżania ról konta).
- Stała `OrganizationUnitType::CODE` zamiast literału `'code'` w modelu, seederze typów jednostek i instalatorze.
- Migracje tworzą tylko schemat, a dane referencyjne dają wyłącznie seedery: jednostki miar wczytuje `MeasureUnitsSeeder` w `db:seed` / `polygon:localization:seed` (nie migracja tabeli). Seedery leżą w `database/seeders/` (przestrzeń nazw `Simplysmart\Polygon\Database\Seeders` bez zmian, osobne mapowanie PSR-4).
- Paczka nie jest już tylko deweloperska: projekty instalują ją w `require` (migracje i modele działają na serwerach); wymaga rozszerzeń `intl` i `bcmath`.
- Strefa `polygon` rejestruje się przez mechanizm stref (`Providers\PolygonZoneServiceProvider`, config `polygon.zone`, `POLYGON_SLUG`); `PolygonAccount` dziedziczy po `ZoneAccount`, `Auth\CreateAccount` zastępuje `Auth\CreateZoneAccount`, a `polygon:account:create` to podklasa `CreateZoneAccountCommand`.
- Pliki instalacji przyjmują każdą zarejestrowaną strefę z kontami (zamiast `InstallFileParser::SUPPORTED_ZONES`), a konta zakładają modelem strefy.
- `RecordHistoryActor` zapisuje strefę żądania i konto z jej guarda (poza strefą nadal guard `web`).

## [0.1.0] - 2026-11-06

### Added

- Szkielet paczki `simplysmart/polygon`: `PolygonServiceProvider` z auto-discovery, Pint (PER, `strict_types`, nagłówek licencji), PHPStan (poziom `max`).
