# Wydania i wdrożenia

Narzędzia paczki `simplysmart/polygon` (przestrzeń nazw `Simplysmart\Polygon\Deploy\`, bez zależności od Laravela). Projekt uruchamia polecenia przez `vendor/bin/`.

| Polecenie | Co robi |
|---|---|
| `vendor/bin/version-release [--dry-run] [--major-confirm] [--root=<katalog>]` | Wydanie SemVer z sekcji `[Unreleased]` w `CHANGELOG.md`: podbija `VERSION` i datuje sekcję. |
| `vendor/bin/deploy <projekt> [--dry-run] [--with-push] [--fresh] [--production\|--no-production] [--root=<katalog>]` | Wdrożenie: przed-produkcja na tym komputerze, potem produkcja przez SSH; z `--fresh` baza od nowa (po potwierdzeniu). |
| `vendor/bin/composer-private [--deploy] [--dry-run] [--root=<katalog>]` | Instalacja paczek prywatnych z `private-packages.json` (poza repozytorium); z `--deploy` manifest wdrożeniowy. |

Polecenia działają na katalogu, z którego je uruchomiono (repozytorium projektu). `--root=` wskazuje inny.

## Paczki prywatne (`vendor/bin/composer-private`)

Paczka prywatna (prywatne repozytorium) nie może pojawić się w publicznym repozytorium projektu — ani w `composer.json`
i `composer.lock`, ani w dokumentacji. Projekt trzyma ją poza gitem:

1. `private-packages.json` (git-ignorowany): `{"require": {"<vendor>/<paczka>": "@dev"}}`.
2. `composer private:install` (`vendor/bin/composer-private`) scala listę z `composer.json` w `composer.private.json`
   (git-ignorowany; przy pierwszym uruchomieniu kopiuje `composer.lock` jako `composer.private.lock`) i uruchamia
   `COMPOSER=composer.private.json composer update <paczki prywatne>`.
3. Paczka trafia do `vendor/` obok publicznych z ogólnego repozytorium path projektu (np. `../packages/*/*`), więc
   jej nazwy nie ma w żadnym pliku repozytorium.

Zwykły `composer install/update/require/remove` działa na publicznym `composer.json` i usuwa paczki prywatne z
`vendor/` — po nim uruchom ponownie `composer private:install`. Projekt ignoruje w gicie `private-packages.json`,
`composer.private.json` i `composer.private.lock`. Bez listy polecenie kończy się sukcesem (publiczny klon nic nie
instaluje).

## Manifest wdrożeniowy (`vendor/bin/composer-private --deploy`)

Lokalnie paczki z repozytoriów `path` (np. `../packages/*/*`) są dowiązaniami, a `composer.lock` zapisuje je jako „z
katalogu”. Na serwerze tych katalogów nie ma, więc `--deploy` buduje osobny manifest (`Deploy\PrivatePackages\DeployLock`):

- `composer.deploy.json`: `composer.json` bez repozytoriów `path`, z repozytorium VCS (adres `git remote origin`) każdej
  paczki z tych katalogów, którą projekt wymaga (`require`, `require-dev`, paczki prywatne), i z dopisanymi paczkami
  prywatnymi (`DeployManifest`);
- `composer.deploy.lock`: kopia `composer.private.lock` (albo `composer.lock`) zaktualizowana przez
  `composer update <paczki> --no-install --no-scripts`, czyli paczki przypięte do commitów z repozytoriów zdalnych, `vendor/` bez zmian.

Stary manifest jest usuwany przed zbudowaniem nowego (także po błędzie composera), więc nie da się wdrożyć
nieaktualnego. Paczka z commitami nieobecnymi w jej `origin` (także bez gałęzi śledzonej) dostaje ostrzeżenie (lock wskaże kod z repozytorium zdalnego; `vendor/bin/deploy --with-push` ją wypycha), paczka
bez `origin` to błąd. Projekt bez paczek z repozytoriów `path` manifestu nie potrzebuje. Composer na komputerze
deweloperskim potrzebuje dostępu do prywatnych repozytoriów (`composer config -g github-oauth.github.com <token>`).
Oba pliki dodaj do `.gitignore` projektu.

## Struktura

- `bin/`: punkty wejścia (`deploy`, `version-release`) i wspólny `autoload.php`, który bierze autoloader Composera projektu.
- `src/Deploy/Console/`: wspólna warstwa poleceń (`Output`, `CommandLine`, `Confirmation`, `ExitCode`).
- `src/Deploy/Release/`: wydania (`Changelog`, `ReleaseBump`, `SemanticVersion`, `ReleasePlanner`…).
- `src/Deploy/Deployment/`: wdrożenia (`DeployCommand`, `DeployOptions`, `ProjectConfig`, `RemoteScript`, `RepositoryCheck`…).
- `src/Deploy/PrivatePackages/`: paczki prywatne i manifest wdrożeniowy (`PrivatePackages`, `DeployLock`, `DeployManifest`, `PathRepositories`, `GitRepositorySource`…).
- `src/Deploy/Process/`: uruchamianie procesów (`ProcessRunner`, `SystemProcessRunner`).
- `resources/deploy/remote.sh`: część serwerowa, uruchamiana przez `bash -s` lokalnie (przed-produkcja) i przez `ssh` (produkcja).
- `resources/deploy/deploy-vendor.env.example`: wzór konfiguracji projektu.

## Wdrożenie (`vendor/bin/deploy`)

1. Z `--with-push` najpierw wypycha paczki z repozytoriów path wymagane przez projekt, które mają commity nieobecne w ich `origin` (`git push --set-upstream origin HEAD`, więc także paczkę bez gałęzi śledzonej), potem projekt; błąd `git push` zatrzymuje wdrożenie, a `--dry-run` tylko wymienia paczki do wypchnięcia.
   Potem sprawdza, czy repozytorium projektu jest wypchnięte. Niewypchnięte commity projektu zatrzymują wdrożenie, chyba że podasz `--with-push`; niewypchnięta paczka bez `--with-push` daje tylko ostrzeżenie (krok 2).
2. Buduje świeży manifest wdrożeniowy (jak `composer-private --deploy`; `--dry-run` tylko wymienia paczki). Błąd zatrzymuje wdrożenie.
3. Aktualizuje przed-produkcję (`PREPRODUCTION_PATH`, klon repozytorium na tym komputerze): kopiuje do niej manifest (i plik instalacji, niżej) i uruchamia `remote.sh`: `git pull`, `composer install --no-dev` z manifestu (bez niego z `composer.lock`), migracje, `polygon:install`, cache. Jej `.env` zostaje bez zmian (środowisko serwera i kolejka — niżej).
4. Pyta, czy wdrożyć produkcję (bez terminala odpowiedź to „nie”; `--production` / `--no-production` pomija pytanie), wysyła manifest do `DEPLOY_PATH` (`scp`) i uruchamia ten sam skrypt na serwerze przez `ssh`. Skrypt dostaje nazwę manifestu w `DEPLOY_COMPOSER_MANIFEST`; bez niej (ręczne uruchomienie) instaluje z `composer.lock`.

### Plik instalacji projektu

Gdy obok konfiguracji projektu (`<projekt>.env`, niżej) leży `<projekt>.json` (format z [instalacja.md](instalacja.md):
konta, role, uprawnienia stref — np. z prawdziwymi adresami personelu, więc poza repozytorium), deploy kopiuje go do
przed-produkcji i wysyła na serwer (`scp`) jako `storage/app/<projekt>.install.json` (zawartość `storage/app/` jest poza
gitem, prawa `600`). `remote.sh` po migracjach uruchamia `php artisan polygon:organizations:seed` (brakujące typy jednostek i organizacja hero, idempotentnie) i `php artisan polygon:install` z plikami z `POLYGON_INSTALL_FILES`
w `.env` i tym plikiem (`DEPLOY_INSTALL_FILE`), bez wpisywania go do `.env`. Instalacja uruchamia się przy każdym
wdrożeniu (także bez pliku projektu), więc nowe uprawnienia i role z plików paczek trafiają do bazy bez ręcznego seeda;
jest idempotentna — tworzy tylko brakujące uprawnienia, role i konta, a istniejącym kontom niczego nie odbiera. Słowników
deploy nie seeduje (pierwsza instalacja: `php artisan db:seed --force`).

Błąd przed-produkcji zatrzymuje wdrożenie. Na serwerze musi istnieć klon repozytorium z `.env.production`, który skrypt kopiuje jako `.env` (APP_KEY serwera zostaje, gdy plik go nie podaje). Konfiguracja projektu (ignorowana przez git, wzór `resources/deploy/deploy-vendor.env.example`) leży w repozytorium projektu w jednym z dwóch układów — katalog projektu ma pierwszeństwo, a plik instalacji `<projekt>.json` deploy bierze z tego samego katalogu co `.env`:

- katalog projektu: `infrastructure/deploy/vendors/<projekt>/<projekt>.env` (i `<projekt>.json`); w katalogu mogą leżeć inne pliki projektu, deploy ich nie wysyła;
- układ płaski: `infrastructure/deploy/vendors/<projekt>.env` (i `<projekt>.json`).

### Baza od nowa (`--fresh`)

`vendor/bin/deploy <projekt> --fresh` wdraża jak zwykle, ale zamiast migracji, seedera typów jednostek i
`polygon:install` odtwarza bazę każdego wdrażanego środowiska od nowa: `php artisan migrate:fresh --force` (**usuwa
wszystkie tabele i dane**: konta z PIN-ami, zaufane urządzenia, organizacje i wszystko, co dodano w aplikacji), potem
`php artisan db:seed --force` z tymi samymi plikami instalacji (słowniki, typy jednostek, organizacja hero, pliki
instalacji paczek i projektu, seedery paczek). `remote.sh` dostaje `DEPLOY_FRESH=1`.

- Każde środowisko pyta osobno: przed-produkcja przed zbudowaniem manifestu, produkcja po decyzji o produkcji — także
  z `--production`. Bez terminala odpowiedź to „nie”.
- Brak zgody dla przed-produkcji przerywa wdrożenie, zanim cokolwiek się zmieni; brak zgody dla produkcji zostawia ją
  bez zmian (przed-produkcja jest już odtworzona).
- `--dry-run --fresh` tylko opisuje plan, o nic nie pyta.
- Konta z plików instalacji powstają od nowa — ich właściciele ustawiają PIN przy pierwszym logowaniu.

```bash
composer deploy -- <projekt> --fresh --no-production   # tylko przed-produkcja od nowa
composer deploy -- <projekt> --fresh --production      # obie bazy od nowa (dwa potwierdzenia)
```

### Środowisko serwera (`.env.production`)

Wzorcem środowiska serwera jest plik na komputerze deweloperskim, w katalogu projektu obok konfiguracji wdrożenia:
`infrastructure/deploy/vendors/<projekt>/<projekt>.env.production` (przed-produkcja:
`<projekt>.env.local`; poza repozytorium, z sekretami). Zmianę środowiska (np. `<STREFA>_SLUG`, `QUEUE_CONNECTION`, poczta)
wprowadza się najpierw w tym pliku, a potem przenosi go ręcznie na serwer jako `.env.production` w `DEPLOY_PATH`
(np. `scp`) — deploy go **nie wysyła**. Przy każdym wdrożeniu `remote.sh` kopiuje `.env.production` serwera jako `.env`
(APP_KEY serwera zostaje, gdy plik go nie podaje), więc zmiana wpisana tylko w `.env` na serwerze zniknie przy
najbliższym wdrożeniu, a zmiana tylko w pliku wzorcowym nie zadziała, dopóki nie trafi na serwer. `.env` przed-produkcji
deploy zostawia bez zmian.

### Kolejka i worker

Maile logowania i bezpieczeństwa stref (kod logowania `Auth\SignIn\IssueSignInCode`, kod odzyskania PIN-u, zmiana
PIN-u, blokada konta, nowe zaufane urządzenie) idą przez kolejkę. Z `QUEUE_CONNECTION=database` (produkcja) zadania
czekają w tabeli `jobs`, dopóki nie wykona ich worker — **deploy workera nie uruchamia**. Bez niego maile nie wychodzą,
a `jobs` rośnie. Na serwerze bez supervisora worker uruchamia cron, co minutę, ścieżkami bezwzględnymi:

```cron
* * * * * cd /home/<konto>/<katalog-aplikacji> && /usr/local/bin/php84 artisan queue:work --stop-when-empty --max-time=55 >> storage/logs/queue.log 2>&1
```

- Cron ma ubogi `PATH`: podaj pełną ścieżkę PHP (`command -v php84` na serwerze) i katalogu aplikacji (bez `~`).
- Kod logowania dochodzi wtedy z opóźnieniem do minuty. Z `QUEUE_CONNECTION=sync` (przed-produkcja) mail wychodzi
  w żądaniu, bez workera, ale odpowiedź logowania trwa dłużej dla istniejącego konta niż dla nieznanego adresu.
- Diagnoza: `php artisan tinker --execute 'echo DB::table("jobs")->count(), " / ", DB::table("failed_jobs")->count();'`
  (zadania czekające / nieudane), `php artisan queue:work --once -v` (jedno zadanie ręcznie, z błędem SMTP na ekranie).
  Zadania z poprzednim `APP_KEY` (`ShouldBeEncrypted`) nie dadzą się odszyfrować — usuń je `php artisan queue:clear`.

## Instalacja w projekcie

1. W `composer.json` projektu dodaj repozytorium `path` (`../packages/simplysmart/polygon`, symlink) i zainstaluj paczkę: `composer require --dev simplysmart/polygon:@dev`.
2. Dodaj do `.gitignore` projektu:

   ```gitignore
   /infrastructure/deploy/vendors/*
   !/infrastructure/deploy/vendors/README.md
   /composer.deploy.json
   /composer.deploy.lock
   ```

3. Opcjonalnie dodaj skrypty do `composer.json` projektu:

   ```json
   "release": "@php vendor/bin/version-release",
   "deploy": ["Composer\\Config::disableProcessTimeout", "@php vendor/bin/deploy"]
   ```

Na serwerach (`composer install --no-dev`) paczki nie ma i nie jest potrzebna: `remote.sh` trafia tam przez standardowe wejście `ssh … bash -s`.

## Testy i analiza (w katalogu paczki)

```bash
composer install
composer test      # PHPUnit
composer analyse   # PHPStan, poziom max
composer lint      # Pint
```

Testy nie używają sieci ani prawdziwego gita. Procesy zastępuje atrapa (`tests/Deploy/Support/FakeProcessRunner`), a pliki leżą w katalogach tymczasowych. Wyjątkiem jest `SystemProcessRunnerTest`, który uruchamia `sh`.
