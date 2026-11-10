# Baza danych i modele

> **Status:** zaimplementowane są kolumny standardowe (`Database\Columns`), pule id (`Database\IdSlots`), historia
> zmian (`Database\History`, triggery MySQL/MariaDB i SQLite, aktor zmian `Database\HistoryActor`), `Models\DataModel` (token, soft delete, casty,
> checksum, scope'y, przejścia stanu `RecordStatusTransitions`, etykiety kolumn), krótkie tłumaczenia `json_i18n` (`HasI18n`), tabele strefy `polygon_*` (`Database\ZoneTables`),
> stockowe tabele Laravela (`users`, `cache`, `jobs`) z wyrównaniem `users` do standardu, słowniki lokalizacji
> z seederami (§14), tabele uprawnień spatie/laravel-permission z zespołami — zespół to organizacja (§15) oraz organizacje z typami jednostek
> i członkostwem kont (§16), zasoby organizacji (§17), kalendarz zasobów (§18) oraz kontrahenci (§19). Warstwa nie obejmuje audytu, cookies, dokumentów prawnych
> ani widoków.

Silniki: MySQL 8 / MariaDB i SQLite (testy, lokalnie). Zasady obowiązują każdą tabelę danych aplikacji.

## 1. Jedno miejsce schematu

- Migracje **wszystkich** tabel bazowych leżą w paczce: `database/migrations/`, ładowane przez `Providers\DatabaseServiceProvider`
  (`loadMigrationsFrom`) — także stockowe migracje Laravela (`create_users_table`, `create_cache_table`,
  `create_jobs_table`, nazwy jak w szkielecie, więc istniejące bazy uznają je za wykonane). Projekt nie ma własnych
  `database/migrations/` ani `database/seeders/`; tabelę `migrations` tworzy sam Laravel.
- Tabelę `users` wyrównuje do standardu migracja `add_standard_columns_to_users_table` (kolumny standardowe w tej
  samej kolejności, `users_history`, pula id); `token` jest tam nullable, bo stockowy model aplikacji go nie nadaje.
  Bez tabeli `users` migracja nic nie robi.
- Tabele strefy z logowaniem (konta, kody logowania, sesje, zaufane urządzenia) tworzy jedna migracja strefy przez
  `Database\ZoneTables`, więc schemat jest identyczny w każdej strefie; strefa paczki to `polygon`
  (`create_polygon_tables`). Konta (`<strefa>_accounts`) mają pełny standard tabel danych.
- **Dwa rodzaje kont:** `<strefa>_accounts` — konta stref (np. `polygon_accounts`:
  model strefy na bazie `Models\ZoneAccount`, np. `Models\PolygonAccount`, guard i provider strefy dopisywane do
  config/auth przez mechanizm stref, [docs/strefy.md](strefy.md), e-mail szyfrowany z `email_hash` = HMAC z `APP_KEY` adresu w postaci kanonicznej (`Auth\EmailIndex`,
  `Contracts\BlindIndex`; konto po adresie: `Queries\Accounts\ZoneAccounts::findByEmail()`), bez hasła — PIN
  przy pierwszym logowaniu, role spatie w guardzie strefy); `users` — konta frontendu (`Models\User`, guard `web`).
  Konto strefy zakłada `php artisan <strefa>:account:create {email} {name} [--role=*] [--skip-existing]`
  (np. `polygon:account:create`; baza `Console\CreateZoneAccountCommand`, akcja `Auth\CreateZoneAccount`, jedna transakcja z rolami; adres zajęty także przez konto usunięte).
- Nazwa pliku migracji zaczyna się od daty (kolejność) i mówi, czyja jest tabela.
- **Migracje i seedery są rozdzielone:** migracja tworzy i zmienia tylko schemat (tabele, historia, pula id), nigdy nie
  wstawia danych ani nie woła seedera; dane referencyjne dają wyłącznie seedery. Seedery leżą w `database/seeders/`
  (`Simplysmart\Polygon\Database\Seeders`, osobne mapowanie PSR-4 w `composer.json`), obok `database/migrations/`.
- Seeder główny `DatabaseSeeder` provider rejestruje pod nazwą `DatabaseSeeder`, więc `php artisan db:seed`
  i `migrate --seed` działają bez seedera w projekcie. Najpierw seedery bazowe polygon (`DatabaseSeeder::BASE`):
  słowniki (`LocalizationSeeder`, `polygon:localization:seed`), dane referencyjne organizacji (`OrganizationsSeeder`,
  `polygon:organizations:seed`, §16), pliki instalacji (`InstallSeeder`, `polygon:install`: uprawnienia, role i konta
  startowe stref, [instalacja.md](instalacja.md)); potem seedery paczek z rejestru `Database\PackageSeeders`, w kolejności
  rejestracji providerów. Paczka dodaje swój seeder (z własnego `database/seeders/`) w `register()` providera:
  `$this->callAfterResolving(PackageSeeders::class, static fn(PackageSeeders $seeders) => $seeders->add(ShopSeeder::class));`.
  Paczka nie tworzy danych demo. Seedery referencyjne są idempotentne
  (rekord szukany po kodzie z `codeColumn()`, nowy dostaje id z puli seederów, zapis przez model) i wolno je
  uruchomić na produkcji. Nie zawierają sekretów ani danych osobowych.

## 2. Kolumny standardowe (`Database\Columns`)

```php
Schema::create('locale_languages', function (Blueprint $table): void {
    Columns::base($table);                  // początek tabeli
    $table->string('code', 8)->unique();    // kolumny domenowe
    Columns::closing($table, i18n: true);   // koniec tabeli
});
app(History::class)->create('locale_languages');   // locale_languages_history + triggery
app(IdSlots::class)->reserve('locale_languages');   // AUTO_INCREMENT od 1000
```

| Metoda | Kolumny |
| --- | --- |
| `Columns::base()` | `id`, `token` (string 64, unique), `parent_id` (FK do tej samej tabeli, nullable), `name`, `status` (string, nullable), `is_active` (domyślnie `false`), `is_default` (domyślnie `false`), `sort_order` (domyślnie 0) |
| `Columns::closing(bool $i18n = false)` | `json_attributes`, `json_default_settings`, `json_tags`, `json_i18n` (tylko gdy `$i18n`), `json_migrations`, `checksum` (char 64, index), `json_archive`, `created_at`, `updated_at`, `deleted_at` |

- To zwykłe metody statyczne, nie makra `Blueprint`. Nazwy kolumn są stałymi `Database\ColumnNames`.
- **Pule id (`Database\IdSlots`):** id 1–999 to sloty rekordów z seederów (`IdSlots::next()` — kolejny,
  `IdSlots::random()` — losowy wolny, np. konta startowe; pełna pula to błąd);
  rekordy użytkowników dostają id od 1000 (`IdSlots::reserve()`: MySQL `AUTO_INCREMENT`, SQLite `sqlite_sequence`).
- **Tabele łączące** (np. `locale_country_language`) nie mają kolumn standardowych, ale mają historię.
- `status` to `string`, nie MySQL `ENUM`; flagi to `boolean()` w migracji i cast `'boolean'` w modelu.

## 4. Historia zmian (`<tabela>_history`)

Każda tabela danych ma tabelę historii zapisywaną **triggerami bazy** (`AFTER INSERT/UPDATE/DELETE`), więc ślad
zostaje także po zmianach z konsoli SQL.

| Kolumna | Znaczenie |
| --- | --- |
| `row_id` | `id` zmienionego rekordu (bez FK) |
| `action` | `insert`, `update`, `delete` |
| `old_values`, `new_values` | JSON całego wiersza przed i po zmianie |
| `actor_zone`, `actor_id`, `request_id` | kto i w jakim żądaniu; `NULL` = system |
| `created_at` | czas zmiany |

- `app(History::class)->create('tabela')` po `Schema::create()`, `->drop('tabela')` w `down()`; migracja zmieniająca
  kolumny kończy się `->sync('tabela')`. `->trackedColumns('tabela')` zwraca kolumny zapisywane przez trigger.
- Historię ma **każda** tabela danych (także `users`, uprawnienia, tabele łączące i konta strefy); bez historii są tylko
  tabele techniczne: `migrations`, `cache*`, `jobs`, `job_batches`, `failed_jobs`, `sessions`, `password_reset_tokens`
  i jednorazowe tabele logowania strefy (`<strefa>_login_challenges`, `<strefa>_sessions`, `<strefa>_trusted_devices`).
  Test projektu porównuje to z bazą po migracjach.
- Dialekty (`Contracts\HistoryTriggers`): `MysqlHistoryTriggers` (aktor ze zmiennych sesji MySQL `@actor_zone`,
  `@actor_id`, `@request_id`) i `SqliteHistoryTriggers` (aktor `NULL`).
- **Kto zmienił:** trigger nie zna użytkownika aplikacji, więc zmienne sesji ustawia `Database\HistoryActor`:
  middleware `Http\Middleware\RecordHistoryActor` na końcu grupy `web` i w grupie strefy `zone.<strefa>` (po
  `StartSession`) zapisuje strefę (poza strefą guard `web`), zalogowane konto z jawnie nazwanego guarda strefy i losowy identyfikator żądania, a po odpowiedzi je czyści; zadanie
  kolejki działa jako „system” z identyfikatorem zadania (`Listeners\SetHistoryActorForJob`). Konsola i SQL
  zapisują `NULL`. Na SQLite aktor jest zawsze `NULL`.
- **Binlog:** MySQL z włączonym binlogiem odrzuca triggery użytkownika bez `SUPER` (błąd 1419,
  `Database\HistoryTriggersRejected`). Administrator serwera ustawia `log_bin_trust_function_creators = 1`
  (np. `SET PERSIST log_bin_trust_function_creators = ON`), a lokalnie można wyłączyć binlog. Hosting musi też dawać
  uprawnienie `TRIGGER`.

## 5. Modele

```
Illuminate\Database\Eloquent\Model
  └─ Simplysmart\Polygon\Models\MainModel     (abstract: token jako klucz trasy)
       ├─ Simplysmart\Polygon\Models\DataModel  (abstract: tabele z kolumnami standardowymi)
       │    ├─ Models\Dictionaries\{Country, Language, Currency, TaxRate, ExchangeRate, Holiday, MeasureUnit}
       │    └─ Models\{Organization, OrganizationUnitType}
       └─ Simplysmart\Polygon\Models\MainAuthenticatable  (konta stref: to samo co DataModel + Authenticatable)
            └─ Models\PolygonAccount               (polygon_accounts, HasRoles w guardzie `polygon`)
Illuminate\Database\Eloquent\Model
  └─ Models\OrganizationMember                (tabela łącząca organization_members)
Illuminate\Foundation\Auth\User
  └─ Simplysmart\Polygon\Models\User           (tabela users, konta frontendu: te same traity co DataModel + HasRoles)
```

- `Models\User` zastępuje `App\Models\User` (projekt nie ma modeli w `app/`): `config/auth.php` wskazuje go jako
  model providera `users`, a provider paczki ustawia go także wtedy, gdy skonfigurowanej klasy nie ma. Fabryka:
  `Database\Factories\UserFactory` (wymaga fakerphp/faker, czyli zależności deweloperskich).

- `DataModel`: `SoftDeletes`, casty i wartości domyślne kolumn standardowych (`HasStandardColumns`), checksum
  (`HasChecksum`), scope'y `active()`, `default()`, `usable()`, odczyt `defaultSetting('klucz.z.kropkami')`.
- **Model jest czysty:** tabela, typy i relacje; bez logiki biznesowej, zapytań wieloetapowych i `Auth`.
- **Typy:** każda kolumna ma cast; kolumna z wartością domyślną w migracji ma ją też w `$attributes`. Test projektu
  sprawdza casty każdego modelu paczki.
- **Nazwy kolumn:** każda kolumna ma etykietę w `lang/<locale>/data.php` paczki, czytaną przez
  `Localization\PackageText::data($model, $kolumna)` (`data.<model>.<kolumna>`, model w snake_case, potem wspólne
  `data.<kolumna>`); test sprawdza kompletność w pl i en. Projekt nadpisuje je w `lang/vendor/polygon/<locale>/data.php`.
- **Relacje tylko w traitach:** `Models/Relations/Has<X>` / `BelongsTo<X>` (`BelongsToCountry`, `BelongsToCurrency`,
  `HasCountries`, `HasOfficialLanguages`, `HasTaxRates`, `SpokenInCountries`, `BelongsToOrganization`,
  `BelongsToParentOrganization`, `BelongsToUnitType`, `BelongsToMember`, `HasOrganizationMembers`, `HasOrganizations`,
  `LocatedInCountry`); model je tylko dołącza.
- **Flagi:** `is_*` — zapisany stan rekordu (boolean); `has_*` — posiadanie cechy, kolumna tylko gdy trzeba po niej
  filtrować; nazwy twierdzące (`is_active`, nie `is_hidden`).
- **`status` i `is_active`:** `status` to standardowy stan wiersza `Enums\RecordStatus` (`draft`, `pending`, `active`,
  `blocked`, `migrating`, `quarantined`, `archived`; nowy wiersz `active`). Zmienia go tylko
  `Database\RecordStatusTransitions` — w transakcji z blokadą wiersza, z kontrolą przejść (`draft → pending → active`,
  `active ↔ blocked`, `active → archived`, dowolny → `migrating` → poprzedni, `quarantined → active`; stan sprzed
  migracji w `json_migrations.record_status.previous`, przywraca go `finishMigration()`), po zatwierdzeniu ze zdarzeniem
  `Events\RecordStatusChanged` (tabela, token, stan przed i po, powód, `Auth\AccountActor`). Model ze stanem
  dziedzinowym nadpisuje `usesRecordStatus()` na `false` i sam podaje cast. `is_active` to wybór administratora
  (widoczność). Wiersz użyteczny = `active` i `is_active` (`usable()`).
- Etykiety enumów (`label()`) to teksty paczki `polygon::enums.*` (`lang/<locale>/enums.php`, odczyt
  `Localization\PackageText`); projekt nadpisuje je w `lang/vendor/polygon/<locale>/enums.php`.

## 6. Warstwy

```
Kontroler → Form Request (walidacja) → Action (zapis) / Query (odczyt) → DTO → widok lub Resource
```

- **Queries/** — odczyt danych (`Queries\Dictionaries\*`: filtry, sortowanie, wpisy domyślne).
- **Actions** — zmiany danych w transakcjach z regułami (`Localization\DictionaryManagement`, `Auth\CreateZoneAccount`,
  `Database\RecordStatusTransitions`).
- Scope'y modelu (`active()`, `default()`, `usable()`) to tylko słownik warunków używany przez Queries. Wyszukiwanie
  „zawiera” buduje `Database\LikePattern` (bez znaków `%` i `_` od użytkownika).

## 7. Słowniki i tłumaczenia (i18n)

- Każdy słownik to osobna tabela z prefiksem tematu (`locale_*`), z pełnym standardem kolumn i historią.
- Krótkie tłumaczenia słownika leżą w `json_i18n` (`{"pl": "polski", "en": "Polish"}`, `HasI18n::translatedName()`).
  Brak tłumaczenia w danym języku nie spada na inny język — pokazuje kod (`translationFallback()`).

## 9. Token zamiast `id`

Publiczne adresy używają wyłącznie `token` (losowe 64 znaki, `HasRouteToken`); `id` służy kluczom obcym.

## 10. Checksum rekordu

`checksum` = SHA-256 kanonicznego JSON kolumn domenowych, bez kolumn technicznych (`ColumnNames::CHECKSUM_EXCLUDED`);
liczy go `DataModel` przy zapisie.

## 11. Kolumny `json_*`

| Kolumna | Przeznaczenie |
| --- | --- |
| `json_attributes` | dodatkowe atrybuty bez własnej kolumny |
| `json_default_settings` | wartości domyślne dla procesów aplikacji (`defaultSetting()`), nie stałe w kodzie; w kontach stref — ustawienia osoby (np. `organization`, domyślny kontekst: docs/strefy.md) |
| `json_tags` | lista tagów rekordu (wyszukiwanie `JSON_CONTAINS`) |
| `json_i18n` | tłumaczenia krótkich słowników (§7) |
| `json_migrations` | metadane integracji |
| `json_archive` | stare wartości po migracjach danych |

## 13. Lista kontrolna nowej tabeli

1. Migracja w `database/migrations/`: `Columns::base()`, kolumny domenowe, `Columns::closing()`.
2. `History::create()` i `IdSlots::reserve()` w tej samej migracji, `History::drop()` w `down()`.
3. Słownik? Prefiks `locale_`, kody ISO w `alpha_code_2`/`alpha_code_3`/`numeric_code` (`Database\CodeColumns`),
   model implementuje `Contracts\DictionaryEntry`, `Columns::closing($table, i18n: true)`.
4. Model dziedziczy `DataModel`, każda kolumna ma cast, wartości domyślne w `$attributes`.
5. Etykiety kolumn w `lang/<locale>/data.php` dla każdego języka (pl, en).
6. Enum statusu w `Enums/` z etykietą w `lang/<locale>/enums.php`; relacje jako traity w `Models/Relations/`.
7. Odczyty w `Queries/`, zmiany w akcjach (transakcja, zapis przez model).
8. Seeder referencyjny w `database/seeders/` (idempotentny, id z `IdSlots::next()`) w `DatabaseSeeder::BASE` albo,
   w paczce, w `Database\PackageSeeders` — nigdy wywołany z migracji.
9. Testy: casty, etykiety, triggery historii zgodne z kolumnami.

## 14. Słowniki lokalizacji

| Tabela | Model | Kolumny domenowe |
| --- | --- | --- |
| `locale_currencies` | `Currency` | `alpha_code_3` (ISO 4217), `numeric_code`, `decimals` |
| `locale_languages` | `Language` | `code` (ISO 639-1/2/3), `native_name`, `is_interface`, `flag_icon` |
| `locale_countries` | `Country` | `alpha_code_2`, `alpha_code_3`, `numeric_code`, `dial_code`, `currency_id`, `is_eu`, `flag_icon`, `continent` (`Enums\Continent`), `region_code` (UN M49), `population` |
| `locale_country_language` | `CountryLanguage` (pivot) | `country_id`, `language_id`, `is_primary` |
| `locale_tax_rates` | `TaxRate` | `country_id`, `type` (`Enums\TaxRateType`), `rate`, `valid_from`, `valid_to`, `note` |
| `locale_exchange_rates` | `ExchangeRate` | `currency_id`, `rate_date`, `rate` (PLN za 1 jednostkę), `source` (`Enums\ExchangeRateSource`), `table_number` |
| `locale_holidays` | `Holiday` | `country_id`, `holiday_date`, `source` (`Enums\HolidaySource`), `is_day_off` |
| `locale_measure_units` | `MeasureUnit` | `country_id`, `code` (symbol), `unece_code` (UN/ECE Rec. 20), `kind` (`Enums\MeasureUnitKind`) |

- `name` jest w języku aplikacji (`app.locale`), `json_i18n` ma nazwy w językach interfejsu (`APP_LOCALES`,
  `polygon.locale.enabled`; `Localization\DictionaryLocales`).
- `is_default` — kraj, język i waluta aplikacji (po jednym, przy pierwszym seedowaniu z `APP_COUNTRY`,
  `APP_LOCALE`, `APP_CURRENCY`; domyślnie Polska, polski, złoty). Nowa instalacja włącza walutę aplikacji z `LocalizationData::ACTIVE_CURRENCIES`
  i tylko języki interfejsu.
- Dane krajów, języków i walut pochodzą z ICU (rozszerzenie `intl`); stawki VAT tylko kraju aplikacji (dane państw UE na 2025 r.;
  Polska w całości z „zw”, pozostałe do weryfikacji w TEDB; stawki innych krajów dodaje administrator); jednostki miar Polski, Wielkiej Brytanii i USA dopisuje
  `MeasureUnitsSeeder` (w `LocalizationSeeder`).
- **Odczyty** (`Queries\Dictionaries`): `DefaultEntries` (wpis `is_default`, a bez niego wpis o kodzie z configu:
  `APP_COUNTRY`, `APP_LOCALE`, `APP_CURRENCY`), `DictionaryList` (pełne słowniki, także wyłączone wpisy, i stawki VAT
  kraju), `DictionarySearch` (języki, kraje, waluty po nazwie i kodzie; kraje po nazwie święta), `TaxRatesInForce`
  (stawki kraju w danym dniu, podstawowa, stawka aplikacji — bez słownika `APP_DEFAULT_VAT`), `ExchangeRatesOn` (kurs
  z dnia, z ostatniego dnia publikacji przed datą — reguła przeliczeń faktur, przeliczenie przez PLN w bcmath),
  `HolidaysIn` (dni wolne w okresie, dzień roboczy), `MeasureUnits` (jednostki kraju).
- **Zmiany** (`Localization\DictionaryManagement`): włączanie i wyłączanie krajów, języków i walut (bez wpisów
  domyślnych i języków interfejsu), nowa stawka VAT od daty (nowa podstawowa kończy poprzednią dzień wcześniej)
  i zakończenie stawki; odmowa to `DictionaryChangeRejected`. Słowniki zarządzane przez administratora:
  `Localization\Dictionary`.
- **Języki interfejsu** (`Contracts\InterfaceLocales`, `Localization\DictionaryInterfaceLocales`): wybrane w słowniku
  (`is_interface`, domyślny `is_default`), zawsze w granicach configu (`APP_LOCALE` + `APP_LOCALES`); bez słownika
  obowiązuje config; wynik w cache, `forget()` po zmianie słownika.
- Dni wolne: `HolidaysSeeder` (w `LocalizationSeeder`) dopisuje ogólnokrajowe święta Polski na lata 2026–2027
  (dane z Nager.Date, `source` = `nager`; nazwa polska, `json_i18n` z angielską) tylko brakującym dniom — dnia wolnego
  wpisanego ręcznie nie nadpisuje. Kolejne lata dopisuje się w `HolidaysSeeder::HOLIDAYS`.
- Kursy walut: `ExchangeRatesSeeder` (w `LocalizationSeeder`) dopisuje kursy tabeli A NBP walut CHF, EUR, GBP i USD
  z dni publikacji 2026-10-29 – 2026-11-05 (`source` = `nbp`, numer tabeli) tylko brakującym parom waluta + dzień —
  kursu wpisanego ręcznie nie nadpisuje. Kolejne dni dopisuje się w `ExchangeRatesSeeder::RATES`.
- Kursy walut i dni wolne mają tabele, modele i odczyty; ich synchronizacja z NBP i Nager.Date
  nie jest jeszcze częścią paczki.
- Dane startowe: `php artisan polygon:localization:seed`. Ponowne uruchomienie dopisuje tylko brakujące wpisy, a wybory
  administratora (włączenie, domyślny, usunięte stawki) zostają.

## 15. Uprawnienia (spatie/laravel-permission, zespół = organizacja)

| Tabela | Kolumny |
| --- | --- |
| `permissions` | kolumny standardowe + `guard_name`; unikalne `name` + `guard_name` |
| `roles` | kolumny standardowe + `guard_name` + `organization_id` (zawsze null — role są wspólne dla organizacji); unikalne `name` + `guard_name` |
| `model_has_permissions`, `model_has_roles` | `id`, `permission_id` / `role_id`, `model_type`, `model_id`, `organization_id` (null — przypisanie globalne); unikalne przypisanie w organizacji |
| `role_has_permissions` | `id`, `permission_id`, `role_id`; unikalna para |

- Nazwy tabel i kolumn: `Permissions\PermissionTables` (kolumna zespołu `TEAM_FOREIGN_KEY` = `organization_id`).
  Konfigurację spatie ustawia `Permissions\PermissionDefaults` (po rejestracji providerów): modele
  `Models\Permissions\{Role,Permission}`, `teams = true`, model zespołu `Models\Organization`; nie publikuj
  `config/permission.php`. Kolumnę zespołu dodaje migracja `add_organization_to_permission_tables` (przypisania sprzed
  niej zostają globalne).
- **Role w organizacji:** konto może mieć w każdej organizacji inne role (np. właściciel w jednej, lekarz w drugiej).
  Nadaje i czyta je `Permissions\OrganizationRoles` (`assign()`, `names()`; organizacja null — role globalne;
  `assignIn()` dla zapisanej organizacji — bez całkowitego id rzuca `LogicException` zamiast nadać role globalne;
  `within()` uruchamia działanie w zespole organizacji i przywraca poprzedni; `holders()` — aktywne konta modelu strefy
  z rolą w organizacji, np. lekarze poradni), niezależnie od zespołu żądania. Role globalne mają hero i konta bez organizacji (`Auth\CreateZoneAccount`,
  `<strefa>:account:create`); role personelu nadaje instalator w organizacjach z pliku ([instalacja.md](instalacja.md)).
- **Zespół żądania:** `Http\Middleware\UseOrganizationPermissions` w grupie middleware strefy ustawia zespół spatie
  (przez `OrganizationRoles::within()`) na bieżącą organizację konta (`Contracts\CurrentOrganization`); hero i konto bez organizacji — null (role globalne).
  `hasRole()`, `can()`, `checkPermissionTo()` i `zone.permission:*` liczą więc role w bieżącej organizacji. Poza
  żądaniem strefy (konsola, kolejka) zespół to null.
- Role i uprawnienia mają pełny standard tabel danych (traity `HasRouteToken`, `HasStandardColumns`, `HasChecksum`,
  soft delete), historię i pulę id; tabele łączące mają `id` zamiast złożonego klucza spatie (dla historii).
- Model z rolami dołącza `Spatie\Permission\Traits\HasRoles`; model inny niż provider guarda podaje `$guard_name`.

## 16. Organizacje

Organizacja to firma korzystająca z aplikacji, wspólna dla stref. Schemat powstaje w jednej migracji
(bez osobnych migracji `add_*`); role konta zależą od organizacji (zespoły spatie, §15), a bieżącą
organizację wybiera kontekst organizacji konta w strefie ([strefy.md](strefy.md), „Kontekst organizacji”); widoki, formularze
i zarządzanie organizacjami dodaje paczka strefy.

| Tabela | Model | Standard | Kolumny domenowe |
| --- | --- | --- | --- |
| `organizations` | `Models\Organization` | pełny, drzewo przez `parent_id` | `is_department`, `is_cooperation` (§20), `unit_type_id`, `short_name`, `color`, dane firmy (`Organization::DETAIL_LENGTHS`) |
| `organization_unit_types` | `Models\OrganizationUnitType` | pełny | `code` (unikalny), `number_prefix`, `icon`, `is_internal`, `is_public`; `json_attributes.fields` — pola podorganizacji typu |
| `organization_members` | `Models\OrganizationMember` | łącząca, z historią | `organization_id`, `model_type`, `model_id`, `timestamps`; unikalne organizacja + konto |

- **Dane firmy** (opcjonalne): `nip` (nieunikalny — oddział może mieć NIP firmy, indeks), `regon`, `krs`, `phone`
  i `mobile_phone` (z prefiksem kraju, `Organization::PHONES`), `email`, `billing_email`, `street`, `postal_code`,
  `city` (`Organization::address()`) i `country_code` (kod alpha-2 słownika krajów, relacja `country()` —
  `Relations\LocatedInCountry`). Nazwa skrócona (`short_name`, do `Organization::SHORT_NAME_MAX_LENGTH` = 100 znaków):
  `shortName()` daje ją, a bez niej pełną nazwę. Kolor (`color`, `#rrggbb`): `color()` bez zapisanego koloru zwraca
  stały kolor z `Organization::COLOR_PALETTE` według id. Ikona z `Organization::ICONS` w `json_attributes.icon` (`icon()`).
- **Departament** (`is_department`, `isDepartment()`): wewnętrzna agregacja — dział jednostki (organizacji bez flagi)
  z własnymi członkami, bez własnych danych; nazwa „Jednostka · Departament” z `contextName()`. Reguły drzewa
  (departament bezpośrednio pod jednostką, bez organizacji pod sobą) egzekwuje paczka zarządzająca organizacjami.
- **Typy jednostek:** słownik z typami domyślnymi (siedziba, placówka, filia, poradnia, oddział, departament, pracownia, zakład,
  rejestracja, jednostka ogólna, dział administracyjny, dział wewnętrzny) w slotach 1–999 z polami podorganizacji
  (`OrganizationUnitType::fields()`), z `Database\Seeders\OrganizationUnitTypeSeeder` (w `OrganizationsSeeder`);
  `displayNumber()` dokleja `number_prefix`. Usunięcie typu zostawia jednostkę bez typu (`nullOnDelete`).
- **Organizacja hero** (`Organization::HERO_ID` = 1, nazwa `Organization::HERO_NAME` = „Simplysmart”, `is_default`;
  `isHero()`): organizacja operatora aplikacji, zakładana automatycznie przez seeder główny (`HeroOrganizationSeeder` w
  `OrganizationsSeeder`, przed plikami instalacji — przywraca usuniętą i wyłączoną, zmienionej nazwy nie nadpisuje).
  Konto, któremu nadano rolę `hero` (`Auth\CreateZoneAccount`, instalator), zostaje jej członkiem, o ile już istnieje (`Organizations\OrganizationMembership::joinHeroOrganization()`),
  a członek organizacji hero pracuje jak hero: w kontekście każdej aktywnej organizacji ([strefy.md](strefy.md),
  „Kontekst organizacji”). Frontend stref jej nie pokazuje — scope `Organization::query()->visible()` (bez niej)
  i `Contracts\CurrentOrganization::contexts()`/`visible()`. Seedery typów i organizacji hero uruchamia
  `OrganizationsSeeder` (`php artisan polygon:organizations:seed`, także w `DatabaseSeeder`).
- **Członkostwo** konta strefy w organizacji jest niezależne od ról: `ZoneAccount::organizations()`
  (`Relations\HasOrganizations`, morph `model`), `Organization::members()`; dodaje i usuwa je
  `Organizations\OrganizationMembership` (`add()`, `remove()`), a aktywne (organizacja włączona i nieusunięta) czyta
  `Queries\Organizations\ActiveMemberships`; aktywną organizację po tokenie z formularza —
  `Queries\Organizations\ActiveOrganizations::findByToken()`. Usunięcie organizacji (fizyczne) kasuje członkostwa (`cascadeOnDelete`),
  zwykle organizację usuwa się miękko.
- Rekord należący do organizacji ma kolumnę `Organization::FOREIGN_KEY` (`organization_id`) i relację
  `Relations\BelongsToOrganization`.

## 17. Zasoby organizacji

Zasób to coś, co organizacja ma i czego używa w pracy: gabinet (pomieszczenie przyjęć), sprzęt albo inny zasób. Właścicielem
jest jedna organizacja; korzystają z niego ona i jej jednostki podrzędne (np. gabinet siedziby w grafiku poradni).
Rezerwacje i grafiki zasobów dodają paczki stref, odwołując się do zasobu kluczem `resource_id`
(`OrganizationResource::FOREIGN_KEY`).

| Tabela | Model | Standard | Kolumny domenowe |
| --- | --- | --- | --- |
| `organization_resources` | `Models\OrganizationResource` | pełny, z historią i pulą id | `organization_id` (właściciel, usunięcie organizacji z zasobami zablokowane), `kind` (`Enums\ResourceKind`: `room`, `equipment`, `other`), `code` (krótki kod, np. „G3”, unikalny w organizacji) |

- Relacja `Organization::resources()` — zasoby, których organizacja jest właścicielem.
- **Odczyt** (`Queries\Organizations\OrganizationResources`): `available()` — aktywne zasoby organizacji i jej przodków
  (opcjonalnie jednego rodzaju), `findByToken()` i `includes()` w tym samym zakresie (formularz jednostki nie wskaże
  zasobu sąsiedniej jednostki), `lineage()` — id organizacji i jej przodków.
- `label()` — „kod · nazwa” do list i grafiku. Nowy zasób to aktywny gabinet (`kind` = `room`).
- Zasoby startowe: pliki instalacji, `organizations.<klucz>.resources` ([instalacja.md](instalacja.md)).

## 18. Kalendarz zasobów

Kalendarz to dostępność zasobu organizacji (grafik) i interakcje na nim (rezerwacje): w przychodni wizyty u lekarza
w gabinecie, w innej branży np. rezerwacje sal. Polygon zna zasób, jednostkę, wykonawcę (konto dowolnej strefy, np.
lekarz) i przedział czasu; znaczenie rezerwacji (np. dane pacjenta) trzyma paczka strefy we własnej tabeli
z kluczem `booking_id` (`ResourceBooking::FOREIGN_KEY`), a reguły wykonawcy (np. rola lekarza w jednostce) sprawdza ona
przed zapisem grafiku.

| Tabela | Model | Kolumny domenowe |
| --- | --- | --- |
| `resource_schedules` | `Models\ResourceSchedule` | `organization_id` (jednostka), `resource_id`, `performer_type`/`performer_id` (morph, opcjonalny), `weekday` (ISO 1–7), `starts_at`, `ends_at` (time), `slot_minutes` (null — bez slotów), `week_interval` (co ile tygodni od tygodnia `valid_from`, 1 — co tydzień), `fills_gaps` (wypełnia wolny czas zasobu, bez wyłączności), `valid_from`, `valid_to` (null — bez końca) |
| `resource_bookings` | `Models\ResourceBooking` | `organization_id`, `resource_id`, wykonawca (morph), `kind` (rodzaj z paczki strefy, np. `<strefa>.visit`), `starts_at`, `ends_at`, `status` (`Enums\BookingStatus`: booked, confirmed, completed, cancelled, no_show — stan dziedzinowy zamiast RecordStatus), `booked_by` i `confirmed_by` (morph konta), `confirmed_at` (czas potwierdzenia) |

- **Grafik** (`Calendar\SaveSchedule`, `ScheduleInput`, `ScheduleConflicts`): w transakcji z blokadą wiersza zasobu
  i wykonawcy odrzuca (`ScheduleRejected`, `ScheduleConflict`) przedział bez slotu albo z okresem ważności kończącym
  się przed początkiem, zasób spoza jednostki i jej przodków oraz nakładanie się na inny grafik tego zasobu albo tego
  wykonawcy w tym dniu tygodnia i pokrywającym się okresie ważności — z cyklem tygodni (`Calendar\WeekCycle`) tylko
  wtedy, gdy cykle mają wspólne tygodnie (dwa grafiki co 2 tygodnie na zmianę nie kolidują). Grafiku z przyszłymi
  rezerwacjami nie można usunąć.
- **Wolne terminy** (`Calendar\FreeSlots`, `Slot`): sloty grafiku wykonawcy w jednostce w danym dniu (dziś — cały grafik, także
  sloty, których godzina już minęła; dni wcześniejsze — brak), bez zajętych przez rezerwacje wykonawcy albo zasobu (w dowolnej jednostce), dni wolnych kraju aplikacji (`HolidaysIn`)
  i — na zasobie wynajętym — dni, w których umowa najmu nie obowiązuje (§20);
  `days()` — najbliższe dni z wolnymi terminami.
- **Rezerwacja** (`Calendar\BookSlot`): blokuje (`FOR UPDATE`) wiersz zasobu, potem wykonawcy i dopiero wtedy sprawdza
  slot — równoległa rezerwacja tego terminu czeka na blokadę i kończy się `SlotUnavailable`. Wywołana w transakcji
  paczki strefy staje się jej częścią (rezerwacja i dane dziedzinowe razem albo wcale).
- **Rezerwacja nadliczbowa** (`BookSlot::bookOverflow()`): ponad wolne sloty, na końcu bloku grafiku ze slotami
  kończącego się o podanej godzinie (`FreeSlots::scheduleEndingAt()` — dzień, ważność, cykl i najem jak przy wolnych
  terminach; blok nie może być z dnia minionego). Każda dostaje własną godzinę w kolejce bloku: pierwsze wolne miejsce
  co `slot_minutes` od końca bloku (`FreeSlots::nextOverflow()` / `ResourceSchedule::nextOverflowAt()` — miejsce odwołanej
  wraca do kolejki), na jeden slot (`ResourceBooking::placeOverflow()`); miejsce liczone pod blokadami, więc równoległe
  zapisy nie dostaną tej samej godziny. `json_attributes.overflow` (`ResourceBooking::OVERFLOW`, `isOverflow()`) ją
  odróżnia, a `json_attributes.overflow_after` (`OVERFLOW_AFTER` — koniec bloku „HH:MM”) wiąże z blokiem
  (`ResourceSchedule::holds()`, także przy usuwaniu grafiku z przyszłymi rezerwacjami). Slotów nie zajmuje (`FreeSlots` ją
  pomija), więc nie blokuje następnego bloku. Rezerwacje sprzed kolejki (bez `overflow_after`, wszystkie o końcu bloku)
  dostają kolejne godziny jednorazowo: `php artisan polygon:calendar:overflow-repair` (`Calendar\OverflowQueueRepair`, od
  dziś; ponownie niczego nie zmienia). Czy wykonawca przyjmuje nadliczbowo — decyduje paczka strefy. Blokady jak przy `book()`.
  **Dostępność zasobu po bloku** (`FreeSlots::roomTakenAfter()`): pierwsza kolizja w oknie slotu kolejnej rezerwacji
  nadliczbowej (`nextOverflow()`)
  (`Calendar\RoomTaken` — od kiedy, powód `Enums\RoomTakenBy`, wykonawca i jednostka): inny grafik zasobu (bez
  wypełniających), okno najmu na wyłączność innej jednostki, rezerwacja zajmująca termin innego wykonawcy (koniec
  własnego najmu kooperacji po bloku kolizją nie jest); null — zasób dostępny. Czy kolizja wyłącza zapis nadliczbowy — decyduje paczka strefy.
- **Stan** (`Calendar\BookingStatusChange`): zapisana → potwierdzona, wykonana, odwołana, nieobecność; potwierdzona →
  wykonana, odwołana, nieobecność; wykonana, odwołana i nieobecność są końcowe; odwołana zwalnia termin. **Lista dnia**: `Calendar\DayBookings` (jednostki, dzień, opcjonalnie rodzaj).
- **Jednostki** (`Queries\Organizations\OrganizationUnits`): organizacja i jej aktywne jednostki podrzędne bez hero —
  zakres pracy konta w strefie.
- Grafik startowy: pliki instalacji, `organizations.<klucz>.schedule` ([instalacja.md](instalacja.md)).

## 19. Kontrahenci

Kontrahent to firma, z którą organizacja pracuje (np. pracodawca kierujący pracowników na badania). Należy do jednej
organizacji i jest dostępny także w jej jednostkach podrzędnych. Paczki stref odwołują się do niego kluczem
`contractor_id` (`Contractor::FOREIGN_KEY`), np. stanowiska i zatrudnienia pracowników.

| Tabela | Model | Kolumny domenowe |
| --- | --- | --- |
| `contractors` | `Models\Contractor` | `organization_id`, `short_name`, `nip` (10 cyfr, unikalny w organizacji), `regon`, `krs`, `street`, `postal_code`, `city`, `country_code`, `email`, `phone`, `notes` |
| `contractor_contacts` | `Models\ContractorContact` | `contractor_id`, `name` (imię i nazwisko), `role`, `phone`, `email`, `is_primary` |
| `contractor_contracts` | `Models\ContractorContract` | `contractor_id`, `name` (numer), `signed_on`, `valid_from`, `valid_to`, `notes`; `status()` — `Enums\ContractStatus` (przyszła, obowiązuje, wygasła) |

- **Zapis** (`Contractors\SaveContractor`, `ContractorData`): NIP i REGON normalizowane do cyfr i sprawdzane, NIP
  unikalny w organizacji (także wśród usuniętych); odmowa — `Contractors\ContractorRejected` z polem formularza.
  `SaveContact` — jeden kontakt główny na kontrahenta; `SaveContract` — umowa nie kończy się przed początkiem.
- **Odczyt** (`Queries\Contractors\ContractorSearch`): kontrahenci organizacji i jej przodków po nazwie, wyszukiwanie po
  początku NIP-u (co najmniej 3 cyfry) albo fragmencie nazwy; `findByToken()`, `includes()` w tym samym zakresie.
- **Identyfikatory** (`Identifiers\Nip`, `Regon`, `Pesel` — z datą urodzenia i płcią) i reguły walidacji
  `Rules\Nip`, `Rules\Regon`, `Rules\Pesel` (komunikaty `identifiers.*`).

## 20. Najem zasobów i kooperacja

Właściciel zasobu (np. gabinetu) wynajmuje go innej organizacji-klientowi na okres i w oknach godzinowych. Najemca
prowadzi na nim własny grafik i rezerwacje w **podorganizacji kooperacji** (`organizations.is_cooperation`, `parent_id`
= organizacja najemcy); właściciel widzi i zapisuje w kalendarzu najemcy tylko przez nią, w zakresie poziomów
udostępnienia umowy.

| Tabela | Model | Kolumny domenowe |
| --- | --- | --- |
| `resource_leases` | `Models\ResourceLease` | `organization_id` (właściciel zasobu), `resource_id`, `lessee_organization_id`, `cooperation_organization_id`, `contractor_contract_id` (opcjonalna umowa kontrahenta właściciela, §19), `name` (numer umowy), `signed_on` (data zawarcia — tylko informacja, bez wpływu na grafik), `valid_from` (rozpoczęcie cyklu i okresu umowy), `valid_to` (null — bezterminowo), `windows` (JSON `[dzień ISO, "HH:MM", "HH:MM"]`, bez okien — cały zasób), `week_interval` (okna co ile tygodni od tygodnia `valid_from`), `mode` (`Enums\LeaseMode`: `windows` — na wyłączność, `on_demand` — na żądanie), `access` (JSON, `Enums\LeaseAccess`), `wallboard_token` (losowy token publicznej tablicy) |

- **Okna** (`Leases\LeaseWindows`): kilka okien w jednym dniu (np. 08:00–12:00 i 18:00–22:00); `covers()` — blok mieści
  się w jednym oknie, `overlaps()`, `isValid()` (bez nakładania się w dniu), `label()`.
- **Udostępnienie** (`Enums\LeaseAccess`): `book` — właściciel zapisuje wizyty w kooperacji (daje też podgląd),
  `view` — grafik i zapisy tylko do odczytu, `wallboard` — publiczna tablica online (`grants()`).
- **Zapis** (`Leases\SaveLease`, `LeaseInput`, `LeaseConflicts`): w transakcji z blokadą wiersza zasobu (jak grafik)
  odrzuca (`LeaseRejected`, `LeaseConflict`) brak numeru, błędne okna albo okres, zasób spoza organizacji właściciela
  i jej przodków, najemcę w drzewie właściciela, kooperację bez flagi, nieaktywną albo nie najemcy, umowę kontrahenta
  spoza właściciela, nakładanie się okien z innym najmem zasobu w pokrywającym się okresie, grafik innej jednostki
  w oknach, a przy zmianie umowy — grafik kooperacji poza nowymi oknami albo okresem. `terminate()` — umowa obowiązuje
  do podanego dnia, grafik kooperacji na zasobie kończy się tego dnia, a zwrócone zapisy po nim są do odwołania;
  `renewWallboardToken()` unieważnia stary link tablicy.
- **Grafik** (§18): właściciel nie układa grafiku w wynajętych oknach (`ScheduleConflict::LeasedOut`), kooperacja tylko
  w oknach i okresie swojej umowy (`OutsideLease`; umowa z końcem wymaga grafiku z końcem). Lekarz może mieć w dniu
  kilka bloków z przerwą — każdy blok to osobny wiersz grafiku. `OrganizationResources` daje kooperacji zasoby
  wynajęte jej w umowach obowiązujących dziś albo później; `owns()` — zasób własny (nie wynajęty).
- **Odczyt** (`Queries\Leases\ResourceLeases`): `ofOwner()`, `findOfOwner()`, `overlapping()`, `ofCooperation()`,
  `channels(owner, access)` — najmy zasobów właściciela (z przodkami) obowiązujące dziś albo później, dające poziom
  udostępnienia; `byWallboardToken()` — obowiązująca dziś umowa z poziomem `wallboard`.
- **Najem na żądanie** (`LeaseMode::OnDemand`): okna wyznaczają godziny, w których najemca może korzystać z zasobu, bez
  wyłączności — nie blokują grafiku właściciela ani innych najmów. Grafik kooperacji musi wypełniać wolny czas
  (`fills_gaps`): nie zajmuje zasobu, a `FreeSlots` daje terminy tylko poza innym grafikiem zasobu, oknami najmów na
  wyłączność innych jednostek i rezerwacjami. `ResourceLeases::bookingCounts()` liczy wizyty umowy w okresie (po stanie)
  do rozliczenia.
- **Tablica** (`Leases\Wallboard`, `WallboardEntry`): godziny przyjęć kooperacji na zasobie w dniu (blok grafiku
  w oknach umowy, wykonawca, zasób, liczba zajętych terminów) — bez danych osób zapisanych.
- Umowy startowe: pliki instalacji, `organizations.<właściciel>.leases` i `is_cooperation` ([instalacja.md](instalacja.md)).
