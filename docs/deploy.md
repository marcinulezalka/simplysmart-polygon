# Wydania i wdrożenia

Narzędzia paczki `simplysmart/polygon` (przestrzeń nazw `Simplysmart\Polygon\Deploy\`, bez zależności od Laravela). Projekt uruchamia polecenia przez `vendor/bin/`.

| Polecenie | Co robi |
|---|---|
| `vendor/bin/version-release [--dry-run] [--major-confirm] [--root=<katalog>]` | Wydanie SemVer z sekcji `[Unreleased]` w `CHANGELOG.md`: podbija `VERSION` i datuje sekcję. |
| `vendor/bin/deploy <projekt> [--dry-run] [--with-push] [--production\|--no-production] [--root=<katalog>]` | Wdrożenie: przed-produkcja na tym komputerze, potem produkcja przez SSH. |
| `vendor/bin/composer-private [--deploy] [--dry-run] [--root=<katalog>]` | Instalacja paczek prywatnych z `private-packages.json` (poza repozytorium); z `--deploy` manifest wdrożeniowy. |

Polecenia działają na katalogu, z którego je uruchomiono (repozytorium projektu). `--root=` wskazuje inny.

## Paczki prywatne (`vendor/bin/composer-private`)

Paczka prywatna (prywatne repozytorium) nie może pojawić się w publicznym repozytorium projektu — ani w `composer.json`
i `composer.lock`, ani w dokumentacji. Projekt trzyma ją poza gitem, jak w admiro:

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
nieaktualnego. Paczka z niewypchniętymi commitami dostaje ostrzeżenie (lock wskaże kod z repozytorium zdalnego), paczka
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

1. Sprawdza, czy repozytorium jest wypchnięte. Niewypchnięte commity zatrzymują wdrożenie, chyba że podasz `--with-push`.
2. Buduje świeży manifest wdrożeniowy (jak `composer-private --deploy`; `--dry-run` tylko wymienia paczki). Błąd zatrzymuje wdrożenie.
3. Aktualizuje przed-produkcję (`PREPRODUCTION_PATH`, klon repozytorium na tym komputerze): kopiuje do niej manifest i uruchamia `remote.sh`: `git pull`, `composer install --no-dev` z manifestu (bez niego z `composer.lock`), migracje, cache. Jej `.env` zostaje bez zmian.
4. Pyta, czy wdrożyć produkcję (bez terminala odpowiedź to „nie”; `--production` / `--no-production` pomija pytanie), wysyła manifest do `DEPLOY_PATH` (`scp`) i uruchamia ten sam skrypt na serwerze przez `ssh`. Skrypt dostaje nazwę manifestu w `DEPLOY_COMPOSER_MANIFEST`; bez niej (ręczne uruchomienie) instaluje z `composer.lock`.

Błąd przed-produkcji zatrzymuje wdrożenie. Na serwerze musi istnieć klon repozytorium z `.env.production`, który skrypt kopiuje jako `.env` (APP_KEY serwera zostaje, gdy plik go nie podaje). Konfiguracja projektu: `infrastructure/deploy/vendors/<projekt>.env` w repozytorium projektu (ignorowana przez git), wzór `resources/deploy/deploy-vendor.env.example`.

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
