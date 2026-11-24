# simplysmart/polygon

Rdzeń aplikacji simplysmart: baza danych, mechanizm stref, fakturowanie, kalendarz, assety i narzędzia wydań oraz
wdrożeń. Opis: `README.md`, tematy: `docs/README.md`.

- **Repozytorium publiczne:** żadnych nazw stref ani paczek prywatnych (nazwy, klasy, trasy, tabele, zmienne) w plikach
  śledzonych i w commitach — przykłady w docs na neutralnych nazwach (`shop`, `<strefa>`).
- **Tylko mechanizmy ogólne:** polygon nie zna paczek, które go używają; strefa dokłada swoje przez punkty rozszerzeń
  (`InstallFiles`, `PackageSeeders`, `PackageAssets`, `ZoneServiceProvider`, kontrakty w `Contracts\`).
- **Bez widoków stref:** ekrany i szablony maili dostarcza paczka strefy; polygon ma własne assety (powiadomienia, opcjonalny motyw Polygon)
  i widoki wyłącznie dokumentacji.
- **Dokumentacja** (`docs/dokumentacja.md`): `wiki/` (biznesowa, użytkownik), `docs/` (techniczna, programista), `preview/`
  (design system). Nowa strona trafia do spisu `documentation.md`. Wszystkie trzy
  są w strefie po zalogowaniu.
- **Układ paczki** (`docs/strefy.md` → „Układ paczki”): klasy w `src/app/`, zasoby w `src/{config,database,lang,public,
  resources}`; w katalogu głównym `bin/` (narzędzia dla projektów), `docs/`, `wiki/`, `preview/`, `tests/` i pliki deweloperskie. Provider
  `Providers\PolygonServiceProvider` buduje ścieżki od `ROOT`.
- **`Deploy\` bez Laravela:** narzędzia wydań i wdrożeń działają bez aplikacji (`bin/autoload.php`); testy w `tests/Deploy`.
- **Schemat i dane:** migracje tylko schemat, dane referencyjne z seederów (`docs/baza-danych.md`, lista kontrolna §13).
- **Testy:** narzędzia bez frameworka (`Deploy`, `Listing`) — `tests/` paczki (`composer test`, bez `tests/Integration`);
  logika zależna od Laravela — `tests/Integration/<Obszar>` paczki (klasa bazowa `Tests\TestCase` projektu, fixture'y
  w `tests/Fixtures`, strefy testowe `alpha`, `beta`, `gamma`, `open`), uruchamiane z projektu zestawem `Packages`
  (`php artisan test --compact vendor/simplysmart/polygon/tests/Integration/<plik>`) i analizowane PHPStanem projektu.
- **Bramki:** `composer check` (Pint, PHPStan max, testy); kiedy — jak w „Quality Gates” projektu.
- **Język:** docs, CHANGELOG, opisy PHPDoc i commity po polsku; identyfikatory po angielsku. PHP ^8.4 (bez składni 8.5).
