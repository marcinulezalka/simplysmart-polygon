# Pliki instalacji (domyślna konfiguracja systemu)

Domyślną konfigurację systemu — strefy, uprawnienia, role i konta startowe — opisują pliki JSON. Stosuje je
`Install\Installer` (pliki czyta `InstallFileParser` z `InstallOrganizationParser` i `InstallValues`, łączy
`ZoneInstallMerger` i `InstallOrganizations`, zapisuje w jednej transakcji `OrganizationInstaller` i `ZoneInstaller`):

```bash
php artisan polygon:install     # same pliki instalacji
php artisan db:seed             # słowniki, organizacje (hero), pliki instalacji, potem seedery paczek (DatabaseSeeder)
```

## Które pliki

1. Plik paczki: `database/install/polygon.json` (zawsze pierwszy).
2. Pliki paczek stref, dodane w `register()` ich providerów:
   `$this->callAfterResolving(InstallFiles::class, static fn(InstallFiles $files) => $files->add($path))`.
3. Pliki projektu z `.env`: `POLYGON_INSTALL_FILES=install/strefy.json,install/konta.json` (`polygon.install.files`,
   ścieżki względem katalogu projektu albo absolutne), w podanej kolejności.
4. Przy wdrożeniu `vendor/bin/deploy <projekt>`: plik `<projekt>.json` obok konfiguracji wdrożenia
   (`infrastructure/deploy/vendors/<projekt>/` albo `infrastructure/deploy/vendors/`) z komputera deweloperskiego, przesłany jako `storage/app/<projekt>.install.json` i dopisany na końcu ([deploy.md](deploy.md)).

Pliki są łączone per strefa: uprawnienia się sumują, rola z pliku stosowanego później zastępuje rolę o tej samej nazwie,
konta są dopisywane (ten sam adres w dwóch plikach to błąd). Organizacje (sekcja główna `organizations`) są wspólne dla
stref: organizacja z pliku stosowanego później zastępuje organizację o tym samym kluczu. Plik projektu może więc dodać do strefy tylko nowe
uprawnienia, role i konta, bez powtarzania ról paczki.

## Katalog ról

Sekcja główna `"roles"` to katalog ról **każdej** zarejestrowanej strefy z kontami — także strefy bez własnej sekcji
w plikach. Rola katalogu ma tylko `"permissions": []` (bez uprawnień) albo `"*"` (wszystkie uprawnienia strefy);
konkretne uprawnienia nadaje jej strefa w `zones.<strefa>.roles` (rola strefy nadpisuje rolę katalogu niezależnie od
kolejności plików). Katalog pliku paczki (`Permissions\BaseRole`, etykiety `polygon::roles.<rola>`):

| Rola | Znaczenie | Katalog |
| --- | --- | --- |
| `guest` | konto bez uprawnień (trasy publiczne i dla zalogowanych) | `[]` |
| `support` | wsparcie techniczne | `[]` |
| `owner` | właściciel strefy | `[]` |
| `hero` | **root** — zawsze wszystkie uprawnienia strefy, także dodane przez pliki projektu | `"*"` |
| `registration` | rejestracja | `[]` |
| `doctor` | lekarz | `[]` |
| `accountant` | księgowość | `[]` |
| `analyst` | analityk | `[]` |
| `manager` | manager | `[]` |

Każda strefa po połączeniu plików musi mieć role obowiązkowe `Permissions\RequiredRole` (`guest`, `support`, `owner`,
`hero`) — katalog paczki je zapewnia. Rola `hero` jest najwyższa: w pliku może wystąpić tylko jako
`"permissions": "*"` (bez `except` i `inherits`), a instalator nadaje jej uprawnienia całej strefy po połączeniu plików.
Nie ma roli `admin`. Etykietę roli w strefie daje `ZoneDefinition::roleLabel()`: najpierw `roles.<strefa>.<rola>`
w tekstach paczki strefy, potem `polygon::roles.<rola>`.

## Strefa `polygon`

Uprawnienia: zestaw strefy administratorów (`zones.view`, `zones.configure`, `accounts.manage`,
`organizations.view`, `organizations.manage`, `owners.view`, `owners.assign`, `settings.view`, `settings.manage`,
`users.manage`, `audit.view`, `heroes.manage`, `system.maintenance`) oraz `accounts.view`, `roles.manage`,
`dictionaries.view`, `dictionaries.manage` (enum `Zones\PolygonPermission`, etykiety `polygon::permissions.polygon.*`).
Role katalogu dostają w niej: `support` — podgląd (`zones.view`, `accounts.view`, `organizations.view`, `owners.view`,
`settings.view`, `dictionaries.view`, `audit.view`), `owner` — wszystko poza `heroes.manage` i `system.maintenance`,
pozostałe — nic. Test projektu pilnuje zgodności enumów z plikiem.

## Format

```json
{
    "roles": {
        "guest": { "permissions": [] },
        "manager": { "permissions": [] },
        "hero": { "permissions": "*" }
    },
    "zones": {
        "polygon": {
            "permissions": ["settings.view", "settings.manage"],
            "roles": {
                "support": { "permissions": ["settings.view"] },
                "owner": { "inherits": ["support"], "permissions": ["settings.manage"] },
                "manager": { "permissions": ["settings.view"] }
            },
            "accounts": [
                { "email": "support@simplysmart.pl", "name": "Simplysmart Support", "roles": ["hero"], "id": "random" }
            ]
        }
    }
}
```

Organizacje i personel (np. w pliku projektu albo `infrastructure/deploy/vendors/<projekt>.json`):

```json
{
    "organizations": {
        "clinic": { "name": "Centrum Medyczne", "short_name": "CM", "unit_type": "placowka", "nip": "1234567890", "city": "Warszawa" },
        "clinic-desk": { "name": "Rejestracja", "parent": "clinic", "unit_type": "rejestracja", "is_department": true,
            "resources": [{ "key": "gabinet-1", "name": "Gabinet 1", "code": "G1" }, { "key": "audiometr", "name": "Audiometr", "kind": "equipment" }] }
    },
    "zones": {
        "shop": {
            "accounts": [
                { "email": "anna@example.com", "name": "Anna Nowak", "roles": ["registration"], "organizations": ["clinic", "clinic-desk"] },
                { "email": "ewa@example.com", "name": "Ewa Lis", "organizations": { "clinic": ["owner"], "clinic-desk": ["doctor"] } }
            ]
        }
    }
}
```

| Klucz | Znaczenie |
| --- | --- |
| `roles.<rola>.permissions` (główne) | katalog ról każdej strefy: `[]` albo `"*"` |
| `zones.<strefa>` | strefa zarejestrowana z kontami (`Contracts\ZoneRegistry`, [docs/strefy.md](strefy.md)), np. `polygon` |
| `permissions` | uprawnienia strefy `zasób.akcja` (kebab-case po obu stronach kropki) |
| `roles.<rola>.permissions` | lista uprawnień z `permissions` albo `"*"` (wszystkie) |
| `roles.<rola>.except` | uprawnienia odjęte od `permissions` i odziedziczonych |
| `roles.<rola>.inherits` | role, których uprawnienia rola przejmuje (bez cykli) |
| `accounts[].email`, `name` | konto strefy (adres zapisywany małymi literami, szyfrowany, z `email_hash`) |
| `accounts[].roles` | role strefy z dowolnego pliku (np. `hero` z pliku paczki); konto z organizacjami dostaje je w każdej swojej organizacji, konto bez organizacji i hero — globalnie; nieznana rola to błąd |
| `accounts[].organizations` | organizacje z `organizations` (dowolnego pliku), których konto jest członkiem — personelem (`organization_members`): lista kluczy (`["clinic"]`) albo obiekt klucz => role konta tylko w tej organizacji (`{"clinic": ["owner"], "clinic-desk": ["doctor"]}`, zespół spatie = organizacja, [baza-danych.md](baza-danych.md) §15); nieznany klucz albo rola to błąd |
| `organizations.<klucz>` | organizacja wspólna dla stref; klucz (kebab-case) to jej identyfikator w plikach, zapisany w `json_attributes.install_key` |
| `organizations.<klucz>.name` | nazwa (wymagana); opcjonalnie `short_name` |
| `organizations.<klucz>.parent` | klucz organizacji nadrzędnej (filia, dział); bez cykli. Zarezerwowany klucz `simplysmart` (`Organization::HERO_KEY`) wpina organizację pod organizację hero (#1 „Simplysmart”), niewidoczną we frontendzie stref (`visible()`, `contexts()`) — organizacje klienta pod rootem operatora; tego klucza nie można zdefiniować ani przypisać kontu (członkiem hero zostaje tylko konto z rolą `hero`) |
| `organizations.<klucz>.unit_type` | kod typu jednostki z `organization_unit_types` (`siedziba`, `placowka`, `filia`, `poradnia`, `oddzial`, `departament`, `pracownia`, `zaklad`, `administracja`, `dzial`, `rejestracja`, `ogolna`); nieznany kod to błąd |
| `organizations.<klucz>.is_department` | `true` — departament jednostki nadrzędnej (nazwa w przełączniku: „Jednostka · Departament”) |
| `organizations.<klucz>.is_cooperation` | `true` — podorganizacja kooperacji najemcy (`parent` = organizacja najemcy), w której jest grafik najemcy na wynajętym zasobie ([baza-danych.md](baza-danych.md) §20) |
| `organizations.<klucz>.resources` | zasoby organizacji (gabinety, sprzęt; [baza-danych.md](baza-danych.md) §17): lista obiektów `key` (kebab-case, unikalny w organizacji — po nim instalacja rozpoznaje zasób), `name` (wymagana), `kind` (`room` — domyślnie, `equipment`, `other`), opcjonalnie `code` (do 16 znaków, unikalny w organizacji); zakładane są tylko brakujące, istniejących instalacja nie zmienia |
| `organizations.<klucz>.schedule` | grafik jednostki (kalendarz zasobów, [baza-danych.md](baza-danych.md) §18): lista obiektów `resource` (klucz zasobu tej organizacji albo jej przodka), `performer` (`{"zone": "<strefa>", "email": "<adres konta>"}` — konto z dowolnego pliku albo bazy), `weekday` (1–7, ISO), `from`, `to` („HH:MM”), `slot` (minuty, 5–240), opcjonalnie `valid_from`, `valid_to` (RRRR-MM-DD; bez początku — dzień instalacji) i `every_weeks` (co ile tygodni, 1–8, licząc od tygodnia `valid_from`; domyślnie 1). Zakładany po kontach, tylko brakujące wpisy (ten sam zasób, wykonawca, dzień i godziny = istniejący), z kontrolą kolizji; nieznany zasób, konto albo kolizja to błąd |
| `organizations.<klucz>.leases` | umowy najmu zasobów tej organizacji ([baza-danych.md](baza-danych.md) §20): lista obiektów `resource` (klucz zasobu tej organizacji), `lessee` (klucz organizacji najemcy), `cooperation` (klucz jej podorganizacji z `is_cooperation` i `parent` = najemca), `number` (numer umowy), opcjonalnie `from`, `to` (RRRR-MM-DD; bez początku — dzień instalacji, bez końca — bezterminowo), `windows` (lista `{"days": [2, 4], "from": "08:00", "to": "12:00"}`, kilka okien w dniu wolno, bez okien — cały zasób), `access` (`book` — zapis wizyt przez właściciela, `view` — podgląd, `wallboard` — publiczna tablica), `every_weeks` (okna co ile tygodni, 1–8, od tygodnia `from`; domyślnie 1 — grafik najemcy musi mieć ten sam cykl albo jego wielokrotność w tych samych tygodniach). Zakładane po organizacjach, przed grafikiem (grafik najemcy musi mieścić się w oknach i okresie umowy — przy umowie z końcem podaj `valid_to` grafiku); umowa o tym samym zasobie, kooperacji i numerze = istniejąca; kolizja z innym najmem albo grafikiem to błąd |
| `organizations.<klucz>.attributes` | atrybuty organizacji do `json_attributes` (np. identyfikatory rejestrowe, które czyta paczka strefy), jak `accounts[].attributes`; dopisywane także do istniejącej organizacji (jej pozostałe dane instalacja nadal zostawia bez zmian) |
| `organizations.<klucz>.<dane firmy>` | `nip`, `regon`, `krs`, `phone`, `mobile_phone`, `email`, `billing_email`, `street`, `postal_code`, `city`, `country_code`, `color` (długości jak w kolumnach); inne pola to błąd |
| `accounts[].attributes` | atrybuty konta do `json_attributes` (np. identyfikatory zawodowe, które czyta paczka strefy): obiekt kluczy `snake_case` z wartościami tekst, liczba, flaga, null albo zagnieżdżony obiekt/lista (do 4 poziomów); klucz z pliku zastępuje ten sam klucz konta — także istniejącego — pozostałe zostają; klucz `install_key` jest zarezerwowany |
| `accounts[].id` | `"random"` — losowe wolne id z puli seederów 1–999 (`IdSlots::random()`, nieprzewidywalne); brak — id od 1000 |

Etykiety uprawnień i ról nie należą do pliku (dodaje je warstwa interfejsu z tłumaczeń).

## Zasady

- **Najpierw walidacja:** wszystkie pliki są sprawdzane i łączone (role obowiązkowe, root), zanim cokolwiek
  trafi do bazy (`InvalidInstallFile` z nazwą pliku i miejscem błędu); zapis idzie w jednej transakcji.
- **Idempotentność:** uprawnienia i role powstają tylko brakujące; rola dostaje **dokładnie** uprawnienia z pliku
  (`syncPermissions`). Organizacja powstaje tylko, gdy w bazie nie ma organizacji z jej kluczem (także w koszu) —
  istniejącej instalacja nie zmienia, więc zmiany z aplikacji zostają, a poprawki danych w pliku po pierwszej instalacji
  nie trafiają do bazy. Członkostwa z pliku są dopisywane kontom nowym i istniejącym, nigdy odbierane. Organizacje
  wymagają typów jednostek z `OrganizationUnitTypeSeeder` (`db:seed`), jeśli plik podaje `unit_type`. Konto powstaje tylko, gdy adresu nie ma w strefie (także wśród usuniętych); istniejącemu
  kontu instalacja dopisuje brakujące role, niczego nie odbiera. Uprawnień, ról i kont spoza plików nie usuwa.
- **Konta bez hasła:** konto strefy zakłada `Auth\CreateZoneAccount` modelem strefy (aktywne, bez PIN-u — właściciel ustawia go przy
  pierwszym logowaniu), jak `<strefa>:account:create`.
- **Produkcja:** instalację wolno uruchomić ponownie i na produkcji (seeder referencyjny). Plików z prawdziwymi
  adresami klientów nie commituje się do publicznych repozytoriów — trzymaj je w projekcie.
