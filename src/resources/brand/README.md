# Marka simplysmart

Pliki źródłowe marki simplysmart. To źródła, nie zasoby serwisu: paczka ich nie serwuje, a do widoków,
maili i dokumentów trafiają ich kopie.

## Zawartość

| Plik | Do czego |
| --- | --- |
| `logo/simplysmart.svg` | wzorzec logotypu (wektor, czarny). Z niego powstają wszystkie pozostałe wersje |
| `logo/png/simplysmart-on-light-{800,1600,2400}.png` | logo czarne, na jasne tło: maile, dokumenty, prezentacje |
| `logo/png/simplysmart-on-dark-{800,1600,2400}.png` | logo białe, na ciemne tło |
| `logo/polygon.svg` | logotyp „Polygon” z błyskawicą zamiast „y”, w kolorze tekstu (`currentColor`) — do osadzenia inline |
| `logo/polygon-on-light.svg` | logotyp „Polygon” czarny, na jasne tło (tryb jasny) |
| `logo/polygon-on-dark.svg` | logotyp „Polygon” biały, na ciemne tło (tryb ciemny) |
| `icon/simplysmart-mark-mole-on-dark.png` | sygnet „S”, biały, na ciemne tło; 1024×1024, przezroczyste tło |
| `icon/simplysmart-mark-mole-on-light.png` | sygnet „S”, czarny, na jasne tło; 1024×1024, przezroczyste tło |

Nazwy `on-light` / `on-dark` mówią o **tle**, na którym logo stoi, nie o kolorze logo.

## Sygnet

Litera „S” w stylu kroju Mole (Myles Hunt Design): dwie grube kreski z zaokrąglonymi końcami,
z gradientem wzdłuż kreski — górna ciemnieje na łuku i chowa się pod środkową belką, dolna belka
zanika w lewo. Narysowana od nowa na podstawie plakatu kroju, nie wyjęta z pliku fontu. Przed
użyciem jako znak towarowy sprawdź licencję kroju albo odróżnij literę od oryginalnego glifu.

## Logotyp „Polygon”

Litery krojem Red Hat Mono Medium (SIL OFL 1.1) zamienione na krzywe, więc plik nie potrzebuje fontu. W miejscu „y” kontur
błyskawicy `lucide:zap` (licencja ISC), bez wypełnienia, od wysokości małych liter do dolnego wydłużenia „g”.

## Kolory

| Nazwa | HEX | Token CSS |
| --- | --- | --- |
| Czerń | `#000000` | `--ss-black` |
| Biel | `#ffffff` | `--ss-white` |

Logo występuje tylko w czerni albo bieli — bez gradientów, cieni i innych kolorów.

## Zasady użycia logo

- Na stronie używaj SVG osadzonego inline z `fill="currentColor"` (kopia `logo/simplysmart.svg`), żeby
  przyjmował kolor tekstu i sam przełączał się w motywie ciemnym.
- PNG tylko tam, gdzie SVG nie działa (maile, dokumenty). Wybierz najmniejszy rozmiar, który
  w docelowym miejscu ma co najmniej 2× piksele szerokości wyświetlania.
- Wokół logo zostaw wolne pole co najmniej wysokości kropki nad „i”.
- Nie rozciągaj, nie obracaj i nie zmieniaj proporcji logo.
