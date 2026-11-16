# simplysmart/polygon

Rdzeń aplikacji simplysmart: baza danych, mechanizm stref, fakturowanie, kalendarz, assety i narzędzia wydań oraz
wdrożeń. Opis: `README.md`, tematy: `docs/README.md`.

- **Repozytorium publiczne:** żadnych nazw stref ani paczek prywatnych (nazwy, klasy, trasy, tabele, zmienne) w plikach
  śledzonych i w commitach — przykłady w docs na neutralnych nazwach (`shop`, `<strefa>`).
- **Tylko mechanizmy ogólne:** polygon nie zna paczek, które go używają; strefa dokłada swoje przez punkty rozszerzeń
  (`InstallFiles`, `PackageSeeders`, `PackageAssets`, `ZoneServiceProvider`, kontrakty w `Contracts\`).
- **Bez widoków:** ekrany i szablony maili dostarcza paczka strefy; polygon ma tylko własne assety (powiadomienia).
- **Układ paczki** (`docs/strefy.md` → „Układ paczki”): klasy w `src/app/`, zasoby w `src/{config,database,lang,public,
  resources}`; w katalogu głównym `bin/` (narzędzia dla projektów), `docs/`, `tests/` i pliki deweloperskie. Provider
  `Providers\PolygonServiceProvider` buduje ścieżki od `ROOT`.
- **`Deploy\` bez Laravela:** narzędzia wydań i wdrożeń działają bez aplikacji (`bin/autoload.php`); testy w `tests/Deploy`.
- **Schemat i dane:** migracje tylko schemat, dane referencyjne z seederów (`docs/baza-danych.md`, lista kontrolna §13).
- **Testy:** narzędzia bez frameworka — `tests/` paczki (`composer test`); logika zależna od Laravela — dziś w testach
  projektu (`tests/Feature/<Obszar>`), docelowo w `tests/Integration` paczki.
- **Bramki:** `composer check` (Pint, PHPStan max, testy); kiedy — jak w „Quality Gates” projektu.
- **Język:** docs, CHANGELOG, opisy PHPDoc i commity po polsku; identyfikatory po angielsku. PHP ^8.4 (bez składni 8.5).
