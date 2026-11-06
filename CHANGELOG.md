# Changelog

Format oparty na [Keep a Changelog](https://keepachangelog.com/pl/1.1.0/).

## [Unreleased]

### Added

- Licencja po polsku (`LICENSE.md`, wersja wiążąca) z tłumaczeniem angielskim (`LICENSE.en.md`) i linkami między nimi; wzór zgłoszenia użytku komercyjnego w `THIRD_PARTY.md`.
- Narzędzia wydań i wdrożeń: `vendor/bin/version-release` (wydanie SemVer z `[Unreleased]` w CHANGELOG.md, `--dry-run`, `--major-confirm`) i `vendor/bin/deploy <projekt>` (przed-produkcja na komputerze deweloperskim, potem po potwierdzeniu produkcja przez SSH; konfiguracje w `infrastructure/deploy/vendors/` projektu), z testami PHPUnit.

## [0.1.0] - 2026-11-06

### Added

- Szkielet paczki `simplysmart/polygon`: `PolygonServiceProvider` z auto-discovery, Pint (PER, `strict_types`, nagłówek licencji), PHPStan (poziom `max`).
