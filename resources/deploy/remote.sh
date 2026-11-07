#!/usr/bin/env bash
#
# Część serwerowa wdrożenia (wysyłana przez `vendor/bin/deploy <projekt>` albo uruchamiana ręcznie
# w katalogu aplikacji):
#   1. Composer 2.8+ w ~/bin/composer (instaluje, gdy go nie ma; oficjalny instalator ze sprawdzeniem sumy),
#   2. .env (kopia .env.production leżącego na serwerze w katalogu aplikacji — deploy nie wysyła środowiska; APP_KEY
#      serwera zostaje, gdy plik go nie podaje; bez .env.production .env zostaje bez zmian), git pull --ff-only,
#   3. token GitHub dla Composera (GITHUB_TOKEN, jeśli podany),
#   4. paczki z composer.lock (--no-dev) albo z manifestu wdrożeniowego przesłanego przez deploy
#      (DEPLOY_COMPOSER_MANIFEST, np. composer.deploy.json z composer.deploy.lock — paczki z repozytoriów git),
#   5. APP_KEY, migracje, typy jednostek i organizacja hero (polygon:organizations:seed), pliki instalacji (polygon:install: uprawnienia, role i konta z plików paczek, POLYGON_INSTALL_FILES
#      z .env i pliku przesłanego przez deploy — DEPLOY_INSTALL_FILE), cache konfiguracji, tras i widoków: czyszczone zaraz
#      po instalacji paczek (nowy kod nie działa ze starym cache tras i widoków) i budowane od nowa na końcu, restart
#      workerów kolejki (queue:restart) i opcjonalne polecenie DEPLOY_RELOAD (np. przeładowanie PHP-FPM — opcache
#      serwera trzyma stary kod i stary plik tras, a artisan czyści tylko cache CLI).
#      Z DEPLOY_FRESH=1 (vendor/bin/deploy --fresh, po potwierdzeniu) zamiast migracji, seedera typów jednostek i instalacji:
#      baza od nowa — migrate:fresh (USUWA WSZYSTKIE DANE) i db:seed z tymi samymi plikami instalacji.
# Z DEPLOY_KEEP_ENV=1 (przed-produkcja na komputerze deweloperskim) .env zostaje bez zmian, a bez COMPOSER_BIN
# i ~/bin/composer skrypt bierze composer z PATH zamiast go instalować.
# Zatrzymuje się przy pierwszym błędzie.
#
# Użycie na serwerze:
#   cd ~/moja-domena.pl && DEPLOY_PHP=php84 bash remote.sh

set -euo pipefail

# PHP przekazuje vendor/bin/deploy (domyślne wartości: ProjectConfig); przy ręcznym uruchomieniu podaj DEPLOY_PHP.
php_bin="${DEPLOY_PHP:?Ustaw DEPLOY_PHP na polecenie PHP 8.4 (np. DEPLOY_PHP=php84 bash remote.sh).}"
# Plik Composera na serwerze: COMPOSER_BIN (np. composer.phar w katalogu aplikacji), domyślnie ~/bin/composer.
composer_bin="${COMPOSER_BIN:-$HOME/bin/composer}"
keep_env="${DEPLOY_KEEP_ENV:-}"
manifest="${DEPLOY_COMPOSER_MANIFEST:-}"
install_file="${DEPLOY_INSTALL_FILE:-}"
fresh="${DEPLOY_FRESH:-}"
reload="${DEPLOY_RELOAD:-}"

fail() { echo "BŁĄD: $*" >&2; exit 1; }
step() { echo "--> $*"; }

command -v "$php_bin" >/dev/null || fail "Brak $php_bin. Ustaw DEPLOY_PHP na polecenie PHP 8.4 na serwerze."
"$php_bin" -r 'exit(PHP_VERSION_ID >= 80400 ? 0 : 1);' || fail "$php_bin to nie PHP 8.4 ($("$php_bin" -r 'echo PHP_VERSION;'))."
[[ -f artisan && -f composer.json ]] || fail "Uruchom w katalogu aplikacji (brak artisan / composer.json w $(pwd))."

if [[ ! -f "$composer_bin" && "$keep_env" == 1 && -z "${COMPOSER_BIN:-}" ]] && command -v composer >/dev/null; then
    composer_bin="$(command -v composer)"
fi
if [[ ! -f "$composer_bin" ]]; then
    step "Instaluję Composera w $composer_bin"
    mkdir -p "$(dirname "$composer_bin")"
    setup="$(mktemp)"
    "$php_bin" -r "copy('https://getcomposer.org/installer', '$setup');" || fail "Nie udało się pobrać instalatora Composera."
    expected="$("$php_bin" -r "echo trim(file_get_contents('https://composer.github.io/installer.sig'));")"
    actual="$("$php_bin" -r "echo hash_file('sha384', '$setup');")"
    [[ -n "$expected" && "$expected" == "$actual" ]] || { rm -f "$setup"; fail "Błędna suma kontrolna instalatora Composera."; }
    "$php_bin" "$setup" --quiet --install-dir="$(dirname "$composer_bin")" --filename="$(basename "$composer_bin")"
    rm -f "$setup"
fi
composer=("$php_bin" "$composer_bin")
if ! "${composer[@]}" --version --no-ansi 2>/dev/null | grep -qE 'version (2\.(8|9|[1-9][0-9])|[3-9])\.'; then
    step "Aktualizuję Composera (wymagany 2.8+ dla PHP 8.4)"
    "${composer[@]}" self-update --2 --no-interaction 2>/dev/null || fail "Nie udało się zaktualizować Composera ($composer_bin)."
fi
step "$("${composer[@]}" --version --no-ansi 2>/dev/null | head -n1)"

rm -f .env.deploy
if [[ "$keep_env" == 1 ]]; then
    step "Przed-produkcja: .env zostaje bez zmian"
elif [[ -f .env.production ]]; then
    step "Kopiuję .env.production serwera jako .env"
    # Plik bez APP_KEY dostaje klucz z obecnego .env: nowy klucz unieważniłby sesje i zaszyfrowane dane.
    env_new="$(mktemp .env.XXXXXX)"
    if [[ -f .env ]]; then chmod --reference=.env "$env_new"; else chmod 600 "$env_new"; fi
    if ! grep -qE '^APP_KEY=.+' .env.production && [[ -f .env ]] && grep -qE '^APP_KEY=.+' .env; then
        grep -vE '^APP_KEY=' .env.production > "$env_new" || true
        grep -E '^APP_KEY=.+' .env | tail -n1 >> "$env_new"
    else
        cat .env.production > "$env_new"
    fi
    mv -f "$env_new" .env
else
    step "Brak .env.production na serwerze — .env zostaje bez zmian"
fi

[[ -f .env ]] || fail "Brak .env. Skopiuj .env.example jako .env i uzupełnij (baza, APP_URL, poczta)."

step "Pobieram kod aplikacji (git pull)"
git pull --ff-only || fail "git pull się nie udał (zmiany lokalne na serwerze albo brak gałęzi śledzonej) — popraw i uruchom ponownie."

# Katalogi, których git nie przenosi (puste), a Laravel ich wymaga (widoki aplikacji, cache, logi).
mkdir -p resources/views storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache

if [[ -n "${GITHUB_TOKEN:-}" ]]; then
    step "Zapisuję token GitHub w konfiguracji Composera"
    "${composer[@]}" config --global github-oauth.github.com "$GITHUB_TOKEN"
fi

if ! grep -qE '^APP_KEY=.+' .env; then
    # Aplikacja nie uruchomi się bez APP_KEY, więc klucz powstaje bez artisana (losowe 32 bajty, base64).
    step "Generuję APP_KEY"
    key="base64:$("$php_bin" -r 'echo base64_encode(random_bytes(32));')"
    if grep -qE '^APP_KEY=' .env; then
        sed -i -E "s|^APP_KEY=.*|APP_KEY=${key}|" .env
    else
        printf '\nAPP_KEY=%s\n' "$key" >> .env
    fi
fi
if [[ -n "$manifest" ]]; then
    manifest_lock="${manifest%.json}.lock"
    [[ -f "$manifest" && -f "$manifest_lock" ]] || fail "Brak $manifest / $manifest_lock — deploy nie przesłał manifestu wdrożeniowego."
    step "Instaluję paczki ($manifest, bez narzędzi deweloperskich)"
    COMPOSER="$manifest" "${composer[@]}" install --no-dev --optimize-autoloader --no-interaction
else
    step "Instaluję paczki (composer.lock, bez narzędzi deweloperskich)"
    "${composer[@]}" install --no-dev --optimize-autoloader --no-interaction
fi
[[ -f vendor/autoload.php ]] || fail "Instalacja nie utworzyła vendor/autoload.php."

# Pliki instalacji: z .env plus plik przesłany przez deploy, bez powtórzeń. Config jest bez cache, więc
# POLYGON_INSTALL_FILES ze środowiska procesu ma pierwszeństwo przed .env (Dotenv go nie nadpisuje).
install_files="$({ grep -E '^POLYGON_INSTALL_FILES=' .env || true; } | tail -n1 | cut -d= -f2- | sed -E "s/^[\"']//; s/[\"']\s*$//")"
if [[ -n "$install_file" ]]; then
    [[ -f "$install_file" ]] || fail "Brak $install_file — deploy nie przesłał pliku instalacji."
    chmod 600 "$install_file"
    case ",$install_files," in
        *",$install_file,"*) ;;
        *) install_files="${install_files:+$install_files,}$install_file" ;;
    esac
fi

step "Czyszczę cache konfiguracji, tras, widoków i zdarzeń"
# Nowy kod bez starego cache: migracje czytają bieżący .env, a trasy i widoki nie pochodzą z poprzedniego wdrożenia.
# Magazyn cache (cache:clear w optimize:clear) dopiero po migracjach — przy pierwszym wdrożeniu tabeli cache jeszcze nie ma.
"$php_bin" artisan config:clear >/dev/null
"$php_bin" artisan route:clear >/dev/null
"$php_bin" artisan view:clear >/dev/null
"$php_bin" artisan event:clear >/dev/null
"$php_bin" artisan clear-compiled >/dev/null

if [[ "$fresh" == 1 ]]; then
    step "Baza od nowa: migrate:fresh (usuwa wszystkie dane)"
    "$php_bin" artisan migrate:fresh --force --no-interaction

    step "Dane startowe: db:seed (słowniki, organizacje, pliki instalacji, seedery paczek)"
    POLYGON_INSTALL_FILES="$install_files" "$php_bin" artisan db:seed --force --no-interaction
else
    step "Migracje"
    "$php_bin" artisan migrate --force

    step "Typy jednostek i organizacja hero (polygon:organizations:seed)"
    # Idempotentny seeder referencyjny: brakujące typy jednostek (np. nowe w paczce) muszą być w bazie, zanim pliki
    # instalacji założą organizacje z `unit_type`.
    "$php_bin" artisan polygon:organizations:seed --no-interaction

    step "Pliki instalacji (polygon:install)"
    POLYGON_INSTALL_FILES="$install_files" "$php_bin" artisan polygon:install --no-interaction
fi

step "Assety paczek (polygon:assets)"
# Zbudowane assety paczek (public/build w repozytorium paczki) pod public/vendor/<paczka>; serwer nie buduje ich sam.
"$php_bin" artisan polygon:assets --no-interaction

step "Cache konfiguracji, tras i widoków"
"$php_bin" artisan optimize:clear >/dev/null
"$php_bin" artisan config:cache
"$php_bin" artisan route:cache
"$php_bin" artisan view:cache

step "Restart workerów kolejki (queue:restart)"
"$php_bin" artisan queue:restart

if [[ -n "$reload" ]]; then
    step "Polecenie po wdrożeniu (DEPLOY_RELOAD): $reload"
    bash -c "$reload" || fail "Polecenie po wdrożeniu się nie udało ($reload) — PHP może serwować stary kod z opcache."
fi

step "Wdrożenie zakończone: $("$php_bin" artisan --version)"
