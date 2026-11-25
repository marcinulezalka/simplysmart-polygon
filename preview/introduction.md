# Design system

- [Zakres](#scope)
- [Co dziedziczą strony powyżej](#inheritance)
- [Nowa strona](#add-page)

Preview to motyw Polygon na żywo: każdy przykład stoi nad swoim kodem i reaguje na tryb kolorystyczny oraz rozmiar tekstu z nagłówka.

<a name="scope"></a>
## Zakres

Motyw strefy `polygon` to tokeny `--pg-*` i komponenty `.pg-*`; używa go ta dokumentacja, a strefa z własnym motywem go nie ładuje. Mechanizmy stref (toasty, dzwonek, panel akcji) mają poza motywem tylko układ, więc taka strefa maluje je swoimi tokenami i pokazuje na własnych stronach Preview.

<a name="inheritance"></a>
## Co dziedziczą strony powyżej

Spis idzie od podstaw, przez komponenty, do całego motywu. Poniższe zasady obowiązują na każdej z tych stron, więc komponenty ich nie powtarzają.

| Zasada | Co dają |
| --- | --- |
| [Bazowe zasady UI stref](../docs/assety.md#bazowe-zasady-ui-stref) | zaokrąglenia, cień, warstwy tła, przyciski i kroje |
| [Tryby kolorystyczne](colors.md#modes) | wartości tokenów w każdym trybie |
| [Rozmiar tekstu](typography.md#sizes) | skalę wszystkich wymiarów w `rem` |
| [WCAG](wcag.md) | kontrast i widoczny fokus w trybach WCAG |

<a name="add-page"></a>
## Nowa strona

Jak dopisać stronę i przykład: [Wiki, Docs i Preview](../docs/dokumentacja.md). Podstawy i komponenty trafiają do swojej grupy, a opis wspólny dla wielu stron — do zasad globalnych.
