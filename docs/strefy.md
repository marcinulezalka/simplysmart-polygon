# Strefy i logowanie

Strefa to wydzielona część aplikacji z własnym segmentem URL, prefiksem nazw tras, guardem, tabelami kont i sesją.
Mechanizm stref polygon (bez kontekstu w adresie, wyglądu stref i dziennika audytu).
**polygon nie ma widoków stref** (ma tylko layout dokumentacji, [dokumentacja.md](dokumentacja.md)): daje logikę, trasy, kontrolery, limity i maile, a ekrany logowania, konta i szablony maili
dostarcza paczka strefy.

## Układ paczki

Paczka (polygon i każda paczka strefy) ma kod i zasoby w `src/` w układzie aplikacji Laravela, a w katalogu głównym
tylko pliki deweloperskie:

```
src/app/          klasy PHP — korzeń PSR-4 (`<Vendor>\<Paczka>\`): Providers/, Console/Commands/, Http/Controllers/,
                  Models/, Enums/, View/Composers/, Support/ i katalogi domen
src/config/       config paczki (scalany w providerze)
src/database/     migrations/, seeders/ (osobne mapowanie PSR-4 `…\Database\Seeders\`), install/
src/lang/         teksty paczki (`<locale>/*.php`)
src/public/       pliki serwowane pod `/vendor/<paczka>/` (build/ commitowany, favicony, obrazy)
src/resources/    views/, scss/, js/, icons/, fonts/, brand/ (źródła marki)
src/routes/       trasy strefy
```

W katalogu głównym: `composer.json`, `vite.config.js` (z `root: 'src'`), `phpstan.neon`, `pint.json`, `tests/`,
`docs/`, `wiki/` i `preview/` (strony dokumentacji, [dokumentacja.md](dokumentacja.md)), `bin/`, `README.md`, `CHANGELOG.md`, `VERSION`, licencje. Provider leży w `src/app/Providers/` i buduje każdą
ścieżkę od jednej stałej `ROOT` (`__DIR__ . '/../..'` — katalog `src/`).

## Strefa w paczce

```php
use Simplysmart\Polygon\Database\PackageSeeders;
use Simplysmart\Polygon\Install\InstallFiles;
use Simplysmart\Polygon\Zones\ZoneServiceProvider;

final class ShopServiceProvider extends ZoneServiceProvider // src/app/Providers/
{
    private const string ROOT = __DIR__ . '/../..'; // src/

    public function register(): void
    {
        $this->mergeConfigFrom(self::ROOT . '/config/shop.php', 'shop');
        $this->loadViewsFrom(self::ROOT . '/resources/views', 'shop');
        $this->loadTranslationsFrom(self::ROOT . '/lang', 'shop');
        $this->loadMigrationsFrom(self::ROOT . '/database/migrations');
        $this->callAfterResolving(InstallFiles::class, static fn(InstallFiles $files) => $files->add(self::ROOT . '/database/install/shop.json'));
        $this->callAfterResolving(PackageSeeders::class, static fn(PackageSeeders $seeders) => $seeders->add(ShopSeeder::class)); // opcjonalnie
    }

    protected function routeNamePrefix(): string { return 'shop'; }          // stały: shop.dashboard
    protected function zoneConfig(): array { return config()->array('shop.zone'); }
    protected function routesPath(): string { return self::ROOT . '/routes'; }
}
```

`boot()` buduje `Zones\ZoneDefinition` (`ZoneDefinitionFactory`), rejestruje ją w `Contracts\ZoneRegistry`, dopisuje
guard i provider do config/auth (`ZoneGuardRegistrar`), grupę middleware `zone.<strefa>` i trasy. Strefa `polygon`
rejestruje się tak samo (`Providers\PolygonZoneServiceProvider`, config `polygon.zone`): ma konta i guard, ale nie ma
ekranów ani tras.

Migracje paczki strefy (`src/database/migrations/`) tworzą tylko schemat; dane referencyjne strefy daje jej seeder
(`src/database/seeders/`), dopisany do `Database\PackageSeeders` — `db:seed` uruchamia go po seederach bazowych polygon
(docs/baza-danych.md §1). Seedera nie woła się z migracji.

## Config strefy

| Klucz | Znaczenie |
| --- | --- |
| `name` | nazwa strefy `^[a-z][a-z0-9_]*$` (guard, cache, ciasteczko sesji, grupa middleware) |
| `slug` | segment URL (domyślnie nazwa; `''` = bez segmentu); nie może być kodem włączonego języka |
| `guard`, `provider` | domyślnie nazwa strefy i `<strefa>_accounts` |
| `model` | model kont, podklasa `Models\ZoneAccount`; bez niego strefa jest publiczna (tylko `public.php`) |
| `url_key` | `true` — ekrany po zalogowaniu (konto, `account.php`, `zone.php`) pod hashem sesji: `/<slug>/<hash>/…` (`Zones\ZoneUrlKey`: 20 znaków `[a-z0-9]`, losowy dla sesji strefy, nowy po wylogowaniu). `route()` dokłada hash sam (`Http\Middleware\ApplyZoneUrlKey`); brak, zły albo cudzy hash to 404 dla zalogowanego (`RequireZoneUrlKey`) — linki z innej sesji, zakładki i adresy zebrane przez roboty nie działają. Niezalogowany z takim linkiem (np. po wylogowaniu albo wygaśnięciu sesji) trafia na logowanie, a po nim na tę stronę z hashem nowej sesji (`ZoneUrlKey::rewrite()` w `Auth\SignIn\CompleteSignIn`). Logowanie i `public.php` zostają pod `/<slug>/…`; maile linkują do logowania. Bez klucza albo `null` — domyślny `polygon.zones.url_key` (`ZONE_URL_KEY`, domyślnie `false`); strefa prywatnej paczki nadpisuje jawnym `true`/`false` tylko w swoim `.env`, bez wpisywania tego ustawienia do publicznego configu projektu. Tylko strefa z logowaniem |
| `role_contexts` | `true` — kontekst konta to para organizacja + jedna rola (sekcja „Kontekst organizacji” → „Kontekst roli”): konto pracuje w jednej swojej roli naraz, a uprawnienia liczą się tylko z niej. Domyślnie `false` (suma ról w organizacji). Tylko strefa z logowaniem |
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

Konto (`<strefa>.account.*`, `/<slug>/account`): `security`, `lock`/`unlock` (blokada ekranu), zmiana PIN-u kodem (kod
wysyłany dopiero po poprawnym polu `current_pin`; żądanie JSON dostaje odpowiedź JSON, błąd to 422),
reset PIN-u (`account.pin.reset`, `DELETE account/pin`, pole `reset_pin` = obecny PIN, `Auth\ResetOwnPin`: PIN usunięty,
wszystkie sesje i zaufane urządzenia unieważnione, bieżąca sesja wylogowana, potwierdzenie e-mailem (`pin-reset`), logowanie z komunikatem
`account.pin_reset_done`; nowy PIN przy następnym logowaniu kodem — `pin_setup` albo po zalogowaniu (niżej); teksty opisu procesu dla widoku:
`account.pin_reset_*`), zapasowy e-mail, zaufane urządzenia, `security/sessions` (wyloguj wszędzie).

Akcje sekcji wracają na stronę `security` z kotwicą sekcji. Strefa z własną stroną sekcji wpisuje jej trasę w `zone.auth`:
`'security_pages' => ['pin' => 'account.security.pin.edit']` (sekcje `pin`, `recovery`, `devices`; nazwa trasy bez prefiksu
strefy) — wracają tam akcje sekcji, dawne adresy `account/pin` i `account/devices` oraz link z maila o nowym urządzeniu
(`Http\SecurityPage`), więc strefa nie powtarza kontrolerów.

Strefa, której `auth.steps` ma `PinStep` bez `PinSetupStep`, definiuje PIN po zalogowaniu (`ZoneAuthSettings::definesPinInAccount()`,
`Auth\MissingPin`): konto bez PIN-u (pierwsze logowanie albo po resecie) wchodzi samym kodem z e-maila i trafia na stronę
sekcji `pin`. Dopóki nie zapisze PIN-u (`POST account.pin.define`, pola `pin` i `pin_confirmation`, `Auth\DefinePin`), każda inna
strona dla zalogowanych na nią prowadzi (`EnsurePinDefined`; JSON — 403), poza wylogowaniem. Zapis unieważnia pozostałe sesje,
a urządzenie wybrane do zapamiętania staje się zaufane dopiero wtedy. Taka strefa nie podaje ekranu `pin_setup`.

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
i błędy walidacji (`$errors`). Teksty domyślne: `src/lang/<locale>/sign_in.php`,
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
| `role()` | aktywna rola w bieżącej organizacji (tylko strefa z `role_contexts`; inaczej, dla hero i bez ról — null) |
| `contexts()` | `list<Organizations\OrganizationContext>` (organizacja, `current`, `default`, `role`; `label($zone)` — „organizacja — rola”) do przełącznika, bez organizacji hero |
| `isHero()` | czy konto jest członkiem organizacji hero |
| `choose($organization, $role = null)` | ustawia kontekst; false dla pary spoza kontekstów konta |
| `offers($organization, $role)` | czy para jest kontekstem konta (walidacja formularzy) |

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

### Kontekst roli (`role_contexts`)

W strefie z `role_contexts: true` kontekst to para **organizacja + jedna rola** konta w niej. Konto z kilkoma rolami
w organizacji (np. lekarz i rejestracja w poradni) pracuje w jednej naraz — uprawnienia, `hasRole()` i
`getRoleNames()` liczą tylko ją — i przełącza się między parami bez ponownego logowania.

- **Aktywna rola** (`CurrentOrganization::role()`): wybrana w sesji strefy dla tej organizacji
  (`polygon.role.<id organizacji>`), o ile konto nadal ją ma; inaczej rola zapisanego domyślnego kontekstu (gdy
  dotyczy tej organizacji); inaczej najszersza (`Permissions\OrganizationRoles::broadest()` — najwięcej uprawnień,
  przy remisie pierwsza po nazwie). Hero i organizacja bez ról konta — bez aktywnej roli.
- **Zawężenie:** `UseOrganizationPermissions` ustawia `Permissions\ActiveRole` (stan żądania, `scoped`), a
  `Models\ZoneAccount::setRelation()` zostawia we wczytanych rolach konta tylko aktywną — w zespole bieżącej
  organizacji i tylko dla konta żądania. `OrganizationRoles::names()` / `assign()` działają na wszystkich rolach
  (`ActiveRole::bypass()`); role konta zalogowanego nadaje się przez `OrganizationRoles::assign()`, nie wprost
  `assignRole()`.
- **Przełącznik:** `contexts()` ma pozycję na każdą rolę konta w organizacji, od najważniejszej
  (`OrganizationRoles::ranked()` — najwięcej uprawnień, przy remisie po nazwie; organizacja bez ról — jedna pozycja
  bez roli). Formularze przełączenia i kontekstu domyślnego wysyłają obok `organization` pole `role`; rola,
  której konto nie ma w organizacji, to błąd pola `organization` (`account.organization.unavailable`). Komunikaty
  podają „organizacja — rola” (`OrganizationContext::label()`).
- **Domyślny kontekst:** `DefaultOrganization::save($account, $organization, $role)` zapisuje parę
  (`json_default_settings.organization` i `.role`); plik instalacji ustawia tylko organizację (rola — najszersza).

## Pływający panel akcji

Panel akcji zawsze widoczny na ekranach strefy po zalogowaniu, z toastami przy nim. Polygon daje mechanizm, strefa —
znaczniki, kolory i treść.

| Część | Polygon | Strefa |
| --- | --- | --- |
| Położenie | `Enums\FloatingPanelPosition` (8: rogi, środki boków, środek góry i dołu; domyślnie `bottom-right`), `FloatingPanel\FloatingPanelPreference` (`json_default_settings.floating_panel`), trasa `<strefa>.account.floating-panel` (PUT, pole `position`, `Http\Controllers\Account\FloatingPanelController`) | formularz wyboru (np. Ustawienia → Wygląd, siatka `FloatingPanelPosition::grid()`) |
| Układ | `resources/scss/floating-panel.scss` (`FloatingPanel\FloatingPanelAssets::ENTRIES`, `@packageAssets`): położenia `.pg-float--<położenie>`, toast obok panelu, kierunki wejścia i wyjścia toastu, przezroczystość w spoczynku | kolory, ramka i cień klas `.pg-float*` i swoich przycisków |
| Zachowanie | `resources/js/floating-panel.js` (`initFloatingPanel()`): rozwinięcie, zwinięcie (przycisk, Esc, kliknięcie obok), jedno otwarte menu; `initNotifications()` wstawia toasty do `[data-pg-toasts]` | wywołanie obu funkcji z wejścia JS strefy |
| Treść | — | korzeń `[data-pg-float]` z `[data-pg-toasts]` i `.pg-float__panel` (`[data-pg-float-toggle]`, `[data-pg-float-body]`, `[data-pg-float-close]`): wyszukiwarka, akcje globalne i opcje widoku ekranu |

- **Toast przy panelu:** położenie z boku — toast stoi obok panelu od strony środka ekranu i chowa się pod panel w
  stronę krawędzi (z prawej w prawo, z lewej w lewo); środek dołu — nad panelem, chowa się w dół; środek góry — pod
  panelem, chowa się w górę. Kierunki to zmienne `--pg-toast-in-*` / `--pg-toast-out-*` animacji z
  `notifications.scss`. Bez `[data-pg-toasts]` toasty stoją w prawym dolnym rogu jak dotąd.
- **Stany:** w spoczynku sam przycisk panelu, lekko przezroczysty (najechanie i fokus — pełna widoczność); po
  kliknięciu treść panelu i fokus w pierwszym polu; menu przycisków (`<details>`) otwierają się od strony środka ekranu.

## Klawiatura numeryczna PIN-u

Klawisze 0–9, „C” (czyści pole) i „OK” (wysyła formularz) przy polu PIN-u. Polygon daje komponent, układ i zachowanie,
strefa — pole, przycisk i kolory.

| Część | Polygon | Strefa |
| --- | --- | --- |
| Klawisze | `<x-polygon::pinpad id="…">` (`resources/views/components/pinpad.blade.php`), teksty `polygon::pinpad.*` | miejsce pod polem, w korzeniu `[data-pg-pinpad]` |
| Układ | `resources/scss/pinpad.scss` (`Pinpad\PinpadAssets::ENTRIES`, `@packageAssets`) | kolory klas `.pg-pinpad__key` i `.pg-pinpad__key--submit` |
| Zachowanie | `resources/js/pinpad.js` (`initPinpad()`) | wywołanie z wejścia JS, pole z `maxlength`, przycisk `[data-pg-pinpad-toggle]` (`hidden`, `aria-controls`, `polygon::pinpad.toggle`) |
| Przycisk formularza | ukryty przy otwartej klawiaturze | atrybut `data-pg-pinpad-submit` |

Najechanie i wciśnięcie klawisza cyfry nie zmienia jego kolorów (najwyżej lekki cień), żeby z daleka nie było widać PIN-u.
Bez JS zostaje zwykłe pole. Podgląd: Preview → „Klawiatura numeryczna PIN-u”.

## Konta i komendy

- `<strefa>:account:create {email} {name} [--role=*] [--organization=<klucz>=<rola>,…]* [--default-organization=<klucz>] [--skip-existing]` — podklasa `Console\Commands\CreateZoneAccountCommand`
  (ustawia `$name`, `$description`, `zoneName()`); akcja `Auth\CreateZoneAccount`.
- `polygon:auth:prune` (co godzinę) — usuwa wygasłe kody logowania i nieaktywne zaufane urządzenia stref.
- Role i uprawnienia strefy z plików instalacji ([instalacja.md](instalacja.md)).
