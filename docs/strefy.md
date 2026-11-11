# Strefy i logowanie

Strefa to wydzielona część aplikacji z własnym segmentem URL, prefiksem nazw tras, guardem, tabelami kont i sesją.
Mechanizm stref polygon (bez kontekstu w adresie, wyglądu stref i dziennika audytu).
**polygon nie ma widoków**: daje logikę, trasy, kontrolery, limity i maile, a ekrany logowania, konta i szablony maili
dostarcza paczka strefy.

## Strefa w paczce

```php
use Simplysmart\Polygon\Database\PackageSeeders;
use Simplysmart\Polygon\Install\InstallFiles;
use Simplysmart\Polygon\Zones\ZoneServiceProvider;

final class ShopServiceProvider extends ZoneServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/shop.php', 'shop');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'shop');
        $this->loadTranslationsFrom(__DIR__ . '/../lang', 'shop');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->callAfterResolving(InstallFiles::class, static fn(InstallFiles $files) => $files->add(__DIR__ . '/../database/install/shop.json'));
        $this->callAfterResolving(PackageSeeders::class, static fn(PackageSeeders $seeders) => $seeders->add(ShopSeeder::class)); // opcjonalnie
    }

    protected function routeNamePrefix(): string { return 'shop'; }          // stały: shop.dashboard
    protected function zoneConfig(): array { return config()->array('shop.zone'); }
    protected function routesPath(): string { return __DIR__ . '/../routes'; }
}
```

`boot()` buduje `Zones\ZoneDefinition` (`ZoneDefinitionFactory`), rejestruje ją w `Contracts\ZoneRegistry`, dopisuje
guard i provider do config/auth (`ZoneGuardRegistrar`), grupę middleware `zone.<strefa>` i trasy. Strefa `polygon`
rejestruje się tak samo (`Providers\PolygonZoneServiceProvider`, config `polygon.zone`): ma konta i guard, ale nie ma
ekranów ani tras.

Migracje paczki strefy (`database/migrations/`) tworzą tylko schemat; dane referencyjne strefy daje jej seeder
(`database/seeders/`), dopisany do `Database\PackageSeeders` — `db:seed` uruchamia go po seederach bazowych polygon
(docs/baza-danych.md §1). Seedera nie woła się z migracji.

## Config strefy

| Klucz | Znaczenie |
| --- | --- |
| `name` | nazwa strefy `^[a-z][a-z0-9_]*$` (guard, cache, ciasteczko sesji, grupa middleware) |
| `slug` | segment URL (domyślnie nazwa; `''` = bez segmentu); nie może być kodem włączonego języka |
| `guard`, `provider` | domyślnie nazwa strefy i `<strefa>_accounts` |
| `model` | model kont, podklasa `Models\ZoneAccount`; bez niego strefa jest publiczna (tylko `public.php`) |
| `url_key` | `true` — ekrany po zalogowaniu (konto, `account.php`, `zone.php`) pod hashem sesji: `/<slug>/<hash>/…` (`Zones\ZoneUrlKey`: 20 znaków `[a-z0-9]`, losowy dla sesji strefy, nowy po wylogowaniu). `route()` dokłada hash sam (`Http\Middleware\ApplyZoneUrlKey`); brak, zły albo cudzy hash to 404 dla zalogowanego (`RequireZoneUrlKey`) — linki z innej sesji, zakładki i adresy zebrane przez roboty nie działają. Niezalogowany z takim linkiem (np. po wylogowaniu albo wygaśnięciu sesji) trafia na logowanie, a po nim na tę stronę z hashem nowej sesji (`ZoneUrlKey::rewrite()` w `Auth\SignIn\CompleteSignIn`). Logowanie i `public.php` zostają pod `/<slug>/…`; maile linkują do logowania. Domyślnie `false`, tylko strefa z logowaniem |
| `home` | klasa `Contracts\ZoneHome` — strona startowa konta po zalogowaniu: `routeFor($account)` zwraca trasę strefy bez prefiksu (np. pulpit roli `registration.dashboard`), liczoną w zespole spatie bieżącej organizacji konta; `null` albo nieistniejąca trasa — `<strefa>.dashboard`. Pierwsze logowanie zawsze tu trafia, kolejne wracają na zamierzoną stronę, jeśli była (ogólny pulpit strefy jako zamierzona strona się nie liczy — `ZoneRedirects::isDashboard()`). Tu trafia też zalogowany na trasie tylko dla gości (np. zakładka z logowaniem) i na wejściu do strefy (`ZoneRedirects::entry()` — gość na logowanie). Domyślnie brak, tylko strefa z logowaniem |
| `session` | nadpisania sesji strefy (`lifetime`, `table`…); domyślny czas `polygon.zones.session_lifetime` |
| `auth` | nadpisania `polygon.auth` (np. `['trusted_devices' => ['enabled' => false]]`) |
| `texts` | namespace tekstów paczki (`<namespace>::sign_in.*`, `roles.<strefa>.*`) |
| `views` | ekrany: klucze `Zones\ZoneView` → widok paczki (`shop::auth.login`); trasy logowania i konta powstają tylko przy komplecie |
| `mail.views` | prefiks widoków maili (`shop::mail`) |

Model kont: `final class ShopAccount extends ZoneAccount { protected $table = 'shop_accounts'; protected string $guard_name = 'shop'; }`,
tabele z migracji paczki: `ZoneTables::create('shop')` / `drop('shop')` (konta, kody logowania, sesje, zaufane urządzenia).

## Trasy

Pliki w `routesPath()` (ścieżki względne, krótkie nazwy; brakujący plik jest pomijany):

| Plik | Dostęp (`Zones\AccessTier`) |
| --- | --- |
| `public.php` | każdy |
| `account.php`, `zone.php` | zalogowane konto strefy (wymaga `model`); trasa `dashboard` to cel po zalogowaniu |

Uprawnienie trasy: `->middleware(ZonePermission::middleware('reports.view'))` — przy kilku (`ZonePermission::middleware('a', 'b')`) wystarczy dowolne — (alias `zone.permission`, uprawnienie spatie
w guardzie strefy, brak = 403). Grupa `zone.<strefa>`: EncryptCookies → AddQueuedCookies → `UseZone` → StartSession →
ShareErrors → `PreventRequestForgeryWithoutCookie` → `RecordHistoryActor` → SubstituteBindings. Każda strefa ma osobne
ciasteczko sesji; konto jednej strefy jest gościem w innej.

## Logowanie (`<strefa>.auth.*`, `/<slug>/signin`)

E-mail → kod z e-maila (`IssueSignInCode`, kolejka) → ustawienie PIN-u (pierwsze logowanie) albo PIN. Ta sama odpowiedź
dla nieznanego adresu, limity prób (`polygon.auth.limits`), blokady po błędnych PIN-ach (`lockout_minutes`), zaufane
urządzenia (bez kodu, PIN nadal wymagany), reset PIN-u kodem na zapasowy adres, wylogowanie (`auth.logout`), wygasły
formularz (419) wraca do logowania. Kroki: `polygon.auth.steps` (`Contracts\SignInStep`).

Konto (`<strefa>.account.*`, `/<slug>/account`): `security`, `lock`/`unlock` (blokada ekranu), zmiana PIN-u kodem,
reset PIN-u (`account.pin.reset`, `DELETE account/pin`, pole `reset_pin` = obecny PIN, `Auth\ResetOwnPin`: PIN usunięty,
wszystkie sesje i zaufane urządzenia unieważnione, bieżąca sesja wylogowana, potwierdzenie e-mailem (`pin-reset`), logowanie z komunikatem
`account.pin_reset_done`; nowy PIN przy następnym logowaniu kodem — `pin_setup`; teksty opisu procesu dla widoku:
`account.pin_reset_*`), zapasowy e-mail, zaufane urządzenia, `security/sessions` (wyloguj wszędzie).

## Widoki strefy (`views`)

| `ZoneView` | Dane widoku (zawsze `$zone`) |
| --- | --- |
| `login` | — (formularz `POST <strefa>.auth.login.store`, pole `email`) |
| `code` | `email`, `codeLength`, `validForMinutes`, `resendIn`, `rememberDeviceDays` (null bez zaufanych urządzeń) |
| `pin_setup` | `pinLength` (`pin`, `pin_confirmation`) |
| `pin` | `pinLength` (`pin`) |
| `forgot_pin` | `codePending`, `codeLength` |
| `unlock` | `name`, `pinLength` |
| `security` | `account`, `currentPinLength`, `pinLength`, `codeLength`, `devices`, `currentSelector`, `recoveryEmail`, `pendingRecoveryEmail` |

Komunikaty: `session(\Simplysmart\Polygon\Http\StatusMessage::KEY)` (kontrolery zapisują je pod tym kluczem)
i błędy walidacji (`$errors`). Teksty domyślne: `lang/<locale>/sign_in.php`,
`account.php`, `emails.php` (strefa nadpisuje we własnym namespace; `ZoneDefinition::signInText()`).

## Maile (`mail.views`)

Szablony `<prefiks>.<szablon>` (HTML, wymagany) i opcjonalnie `<prefiks>.text.<szablon>`; zawsze `$mailZone`:

| Szablon | Dane |
| --- | --- |
| `login-code`, `security-code` | `accountName`, `code`, `validForMinutes` (+ `purpose` w `security-code`) |
| `account-locked` | `accountName`, `lockedUntil` |
| `pin-changed` | `accountName`, `changedAt` |
| `pin-reset` | `accountName`, `resetAt` (reset PIN-u przez właściciela, teksty `emails.pin_reset.*`) |
| `new-trusted-device` | `accountName`, `deviceName`, `registeredAt`, `devicesUrl` |
| `device-token-reused` | `accountName` |

## Kontekst organizacji

Konto zalogowane w strefie pracuje w kontekście jednej organizacji (docs/baza-danych.md §16). Odczyt i wybór daje
`Contracts\CurrentOrganization` (`Organizations\SessionCurrentOrganization`, wybór w sesji strefy — każda strefa ma
własny kontekst):

| Metoda | Wynik |
| --- | --- |
| `get()`, `id()` | bieżąca organizacja; bez wyboru (albo gdy wybrana przestała być dostępna) domyślna, zapisana w sesji: domyślna organizacja konta (`json_default_settings.organization`), o ile nadal jest jego kontekstem, inaczej hero — organizacja hero, pozostali — najstarsze członkostwo; gość i konto bez organizacji — null |
| `visible()` | bieżąca organizacja do pokazania we frontendzie (organizacja hero → null) |
| `contexts()` | `list<Organizations\OrganizationContext>` (organizacja, `current`, `default`) do przełącznika, bez organizacji hero |
| `isHero()` | czy konto jest członkiem organizacji hero |
| `choose()` | ustawia kontekst; false dla organizacji spoza kontekstów konta |

- **Konteksty konta** (`Queries\Organizations\ActiveMemberships`): członkostwa w organizacjach aktywnych i nieusuniętych,
  od najstarszego. Członek organizacji hero dostaje organizację hero i wszystkie pozostałe aktywne organizacje (po
  nazwie) — bez zakładania członkostw.
- **Przełączenie:** formularz POST na `<strefa>.account.organization.switch` (`account/organization`, pole
  `organization` = token organizacji, `Http\Controllers\Account\OrganizationSwitchController`); potem pulpit z
  komunikatem `status` (`account.organization.switched`), a organizacja spoza kontekstów to błąd pola
  (`account.organization.unavailable`). Teksty przełącznika: `account.organization.*`.
- **Domyślna organizacja po zalogowaniu** (`Organizations\DefaultOrganization`): zapisana per konto w
  `json_default_settings.organization` (id organizacji; inne klucze kolumny zostają). Ustawia ją sama osoba —
  formularz POST na `<strefa>.account.organization.default` (`account/organization/default`, to samo pole
  `organization`, `Http\Controllers\Account\OrganizationDefaultController`; powrót na poprzednią stronę z komunikatem
  `account.organization.default_saved`, bieżący kontekst się nie zmienia) — albo plik instalacji
  (`accounts[].defaults.organization`, docs/instalacja.md). Działa od następnej sesji; organizacja, która przestała być
  kontekstem konta, jest pomijana.
- Kontekst wybiera też uprawnienia: role i uprawnienia konta liczą się w bieżącej organizacji (zespół spatie =
  organizacja, `Http\Middleware\UseOrganizationPermissions`; docs/baza-danych.md §15). Hero i konto bez organizacji
  mają role globalne.

## Konta i komendy

- `<strefa>:account:create {email} {name} [--role=*] [--organization=<klucz>=<rola>,…]* [--default-organization=<klucz>] [--skip-existing]` — podklasa `Console\CreateZoneAccountCommand`
  (ustawia `$name`, `$description`, `zoneName()`); akcja `Auth\CreateZoneAccount`.
- `polygon:auth:prune` (co godzinę) — usuwa wygasłe kody logowania i nieaktywne zaufane urządzenia stref.
- Role i uprawnienia strefy z plików instalacji ([instalacja.md](instalacja.md)).
