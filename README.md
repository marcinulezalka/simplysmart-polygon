# polygon

Narzędzia deweloperskie aplikacji simplysmart: wydania SemVer (`vendor/bin/version-release`) i wdrożenia przed-produkcja → produkcja (`vendor/bin/deploy`), opis: [docs/deploy.md](docs/deploy.md). Instalowana tylko w środowisku deweloperskim (`require-dev`), więc nie trafia na serwery (`composer install --no-dev`).

Paczka Composer: `simplysmart/polygon`, namespace `Simplysmart\Polygon\`.

## Wymagania

- PHP ^8.4, Laravel 13
- provider `Simplysmart\Polygon\PolygonServiceProvider` rejestruje się sam (auto-discovery)

## Instalacja w projekcie

Paczka leży lokalnie w `/var/www/packages/simplysmart/polygon`. W `composer.json` projektu:

```json
"repositories": [
    { "name": "simplysmart-polygon", "type": "path", "url": "../packages/simplysmart/polygon", "options": { "symlink": true } }
]
```

```bash
composer require --dev simplysmart/polygon:@dev
```

Kod aplikacji nie może zależeć od klas tej paczki, bo na przed-produkcji i produkcji jej nie ma.

Konfiguracje wdrażanych projektów leżą w projekcie: `infrastructure/deploy/vendors/<projekt>.env` (wzór `resources/deploy/deploy-vendor.env.example`).

## Jakość kodu

```bash
composer install   # raz, zależności paczki
composer test      # PHPUnit
composer lint      # Pint, preset PER + strict_types i nagłówek licencji
composer analyse   # PHPStan, poziom max
```

## Licencja

Simplysmart Restricted Source License 1.0 — © 2026 Marcin Ulezalka.

- [Polski (wiążąca)](LICENSE.md)
- [English (translation)](LICENSE.en.md)

Użytek komercyjny wymaga wpisu w pliku atrybucji projektu — wzór w [THIRD_PARTY.md](THIRD_PARTY.md).
