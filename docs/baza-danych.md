# Baza danych i modele

> **Status:** zaimplementowane są kolumny standardowe (`Database\Columns`), pule id (`Database\IdSlots`), historia
> zmian (`Database\History`, triggery MySQL/MariaDB i SQLite, aktor zmian `Database\HistoryActor`), `Models\DataModel` (token, soft delete, casty,
> checksum, scope'y, przejścia stanu `RecordStatusTransitions`, etykiety kolumn), krótkie tłumaczenia `json_i18n` (`HasI18n`), tabele strefy `polygon_*` (`Database\ZoneTables`),
> stockowe tabele Laravela (`users`, `cache`, `jobs`) z wyrównaniem `users` do standardu, słowniki lokalizacji
> z seederami (§14) i tabele uprawnień spatie/laravel-permission bez zespołów (§15). Warstwa pochodzi z rdzenia
> `simplysmart/admiro` (kopia, bez organizacji, zespołów uprawnień, audytu, cookies i dokumentów prawnych); numeracja sekcji
> jest ta sama co w admiro.

Silniki: MySQL 8 / MariaDB i SQLite (testy, lokalnie). Zasady obowiązują każdą tabelę danych aplikacji.

## 1. Jedno miejsce schematu

- Migracje **wszystkich** tabel bazowych leżą w paczce: `database/migrations/`, ładowane przez `PolygonServiceProvider`
  (`loadMigrationsFrom`) — także stockowe migracje Laravela (`create_users_table`, `create_cache_table`,
  `create_jobs_table`, nazwy jak w szkielecie, więc istniejące bazy uznają je za wykonane). Projekt nie ma własnych
  `database/migrations/` ani `database/seeders/`; tabelę `migrations` tworzy sam Laravel.
- Tabelę `users` wyrównuje do standardu migracja `add_standard_columns_to_users_table` (kolumny standardowe w tej
  samej kolejności, `users_history`, pula id); `token` jest tam nullable, bo stockowy model aplikacji go nie nadaje.
  Bez tabeli `users` migracja nic nie robi.
- Tabele strefy z logowaniem (konta, kody logowania, sesje, zaufane urządzenia) tworzy jedna migracja strefy przez
  `Database\ZoneTables`, więc schemat jest identyczny w każdej strefie; strefa paczki to `polygon`
  (`create_polygon_tables`). Konta (`<strefa>_accounts`) mają pełny standard tabel danych.
- **Dwa rodzaje kont:** `polygon_accounts` — konta strefy `polygon` (jak `admiro_accounts` w admiro: model
  `Models\PolygonAccount`, guard i provider `polygon` / `polygon_accounts` dopisywane do config/auth przez
  `Zones\PolygonZone`, e-mail szyfrowany z `email_hash` = HMAC z `APP_KEY` (`Contracts\BlindIndex`), bez hasła — PIN
  przy pierwszym logowaniu, role spatie w guardzie `polygon`); `users` — konta frontendu (`Models\User`, guard `web`).
  Konto strefy zakłada `php artisan polygon:account:create {email} {name} [--role=*] [--skip-existing]`
  (akcja `Auth\CreateAccount`, jedna transakcja z rolami; adres zajęty także przez konto usunięte).
- Nazwa pliku migracji zaczyna się od daty (kolejność) i mówi, czyja jest tabela.
- Seedery to klasy w `src/Database/Seeders/` (`Simplysmart\Polygon\Database\Seeders`). Seeder główny
  `DatabaseSeeder` (słowniki lokalizacji) provider rejestruje pod nazwą `DatabaseSeeder`, więc `php artisan db:seed`
  i `migrate --seed` działają bez seedera w projekcie: słowniki (`polygon:localization:seed`), potem pliki instalacji
  (`InstallSeeder`, `polygon:install`: uprawnienia, role i konta startowe stref, [instalacja.md](instalacja.md)).
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
  middleware `Http\Middleware\RecordHistoryActor` na końcu grupy `web` (po `StartSession`) zapisuje guard `web`,
  zalogowanego użytkownika z jawnie nazwanego guarda i losowy identyfikator żądania, a po odpowiedzi je czyści; zadanie
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
       │    └─ Models\Dictionaries\{Country, Language, Currency, TaxRate, ExchangeRate, Holiday, MeasureUnit}
       └─ Simplysmart\Polygon\Models\MainAuthenticatable  (konta stref: to samo co DataModel + Authenticatable)
            └─ Models\PolygonAccount               (polygon_accounts, HasRoles w guardzie `polygon`)
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
  `HasCountries`, `HasOfficialLanguages`, `HasTaxRates`, `SpokenInCountries`); model je tylko dołącza.
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
- **Actions** — zmiany danych w transakcjach z regułami (`Localization\DictionaryManagement`, `Auth\CreateAccount`,
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
| `json_default_settings` | wartości domyślne dla procesów aplikacji (`defaultSetting()`), nie stałe w kodzie |
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
8. Seeder referencyjny (idempotentny, id z `IdSlots::next()`).
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
  `MeasureUnitsSeeder` (także migracja tabeli).
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
- Kursy walut i dni wolne mają tabele, modele i odczyty; ich synchronizacja z NBP i Nager.Date (w admiro
  `ExchangeRateSync`, `HolidaySync`) nie jest jeszcze częścią paczki.
- Dane startowe: `php artisan polygon:localization:seed`. Ponowne uruchomienie dopisuje tylko brakujące wpisy, a wybory
  administratora (włączenie, domyślny, usunięte stawki) zostają.

## 15. Uprawnienia (spatie/laravel-permission, bez zespołów)

| Tabela | Kolumny |
| --- | --- |
| `permissions` | kolumny standardowe + `guard_name`; unikalne `name` + `guard_name` |
| `roles` | kolumny standardowe + `guard_name`; unikalne `name` + `guard_name` |
| `model_has_permissions`, `model_has_roles` | `id`, `permission_id` / `role_id`, `model_type`, `model_id`; unikalne przypisanie |
| `role_has_permissions` | `id`, `permission_id`, `role_id`; unikalna para |

- Nazwy tabel i kolumn: `Permissions\PermissionTables`. Konfigurację spatie ustawia `Permissions\PermissionDefaults`
  (po rejestracji providerów): modele `Models\Permissions\{Role,Permission}`, `teams = false`; nie publikuj
  `config/permission.php`.
- Role i uprawnienia mają pełny standard tabel danych (traity `HasRouteToken`, `HasStandardColumns`, `HasChecksum`,
  soft delete), historię i pulę id; tabele łączące mają `id` zamiast złożonego klucza spatie (dla historii).
- Model z rolami dołącza `Spatie\Permission\Traits\HasRoles`; model inny niż provider guarda podaje `$guard_name`.
