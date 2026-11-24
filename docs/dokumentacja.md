# Wiki, Docs i Preview

Trzy systemy dokumentacji w konwencji docs Laravela i w jednym układzie trzech kolumn (spis bazowy, treść, spis treści
strony). Kod: `src/app/Documentation/`, widoki: `src/resources/views/documentation/` — jedyne widoki polygonu.

| System | Odbiorca | Katalog | Adres | Dostęp |
| --- | --- | --- | --- | --- |
| Wiki | użytkownik aplikacji (dokumentacja biznesowa) | `wiki/` | `/<slug strefy>/wiki/{strona}` (`<strefa>.wiki.show`) | każde zalogowane konto strefy |
| Docs | programista (dokumentacja techniczna) | `docs/` | `/<slug strefy>/docs/{strona}` (`<strefa>.docs.show`) | każde zalogowane konto strefy |
| Preview | programista i projektant (design system) | `preview/` | `/<slug strefy>/preview/{strona}` (`<strefa>.preview.show`) | każde zalogowane konto strefy |

Żaden system nie jest publiczny: gość trafia na logowanie strefy, a po nim na stronę. Wszystkie strony mają `noindex`.
Widzi je każde konto strefy, więc strony nie zawierają sekretów ani danych osobowych.

## Strona

- Plik `<strona>.md` (kebab-case, w Wiki i Preview po angielsku), treść po polsku. `README.md` nie jest stroną.
- Układ: `# Tytuł`, lista linków do sekcji, treść, a przed każdym nagłówkiem `<a name="kotwica"></a>`. Lista jest dla GitHuba;
  w aplikacji znika, bo spis treści powstaje z nagłówków `##` i `###`. Nagłówek bez kotwicy dostaje slug jak na GitHubie.
- Alerty: `> [!NOTE]`, `> [!TIP]`, `> [!IMPORTANT]`, `> [!WARNING]`, `> [!CAUTION]`.
- Linki względne: `strona.md#kotwica`, do innego systemu `../wiki/strona.md`. Działają na GitHubie i w aplikacji
  (`Documentation\LinkRewriter`).
- Każda strona jest w spisie `documentation.md` (`- ## Grupa`, pod nią `- [Tytuł](strona.md)`); test pilnuje spisu i linków.
- Strona jest przetwarzana raz i trzymana w cache; zmiana pliku odświeża ją sama. Zmiana wyniku przetwarzania —
  podnieś `MarkdownRenderer::VERSION`.

## Przykład w Preview

Blok kodu z opisem `blade preview` pokazuje się jako żywy przykład nad swoim kodem (`Documentation\PreviewExample`): Blade
renderuje się w osobnej ramce z assetami źródła, w bieżącym trybie koloru i rozmiarze tekstu. Przykłady polygonu
używają motywu Polygon ([assety.md](assety.md#motyw-polygon)); komponenty strefy pokazuje jej paczka.

## Trasy w strefie

`Http\DocumentationRoutes` rejestruje Wiki, Docs i Preview w każdej strefie z ekranami (`ZoneDefinition::hasScreens()`).
Aplikacja bez takiej strefy nie ma dokumentacji. W strefie z `url_key` strony są pod hashem sesji, a
`/<slug>/<system>/{strona}` (`<strefa>.<system>.entry`) to wejście dla linków spoza strefy: gość loguje się i wraca na stronę.

## Paczka strefy

Paczka dopisuje swoje katalogi w `register()` providera; jej strony mają adres `<klucz>/<strona>` i stoją w spisie po stronach
polygonu:

```php
$this->callAfterResolving(DocumentationSources::class, static function (DocumentationSources $sources): void {
    $sources->add(new DocumentationSource(DocumentationSystem::Wiki, 'shop', self::ROOT . '/../wiki'));
    $sources->add(new DocumentationSource(DocumentationSystem::Docs, 'shop', self::ROOT . '/../docs', zone: 'shop'));
    $sources->add(new DocumentationSource(DocumentationSystem::Preview, 'shop', self::ROOT . '/../preview', assets: ['shop' => ['resources/scss/app.scss']]));
});
```

Źródło ze strefą widzą tylko jej konta; Docs paczki muszą ją nazwać, a Wiki i Preview bez niej są w każdej strefie z ekranami. Adres strony na ekranie paczki (np. w podpowiedzi „Wiki”) daje
`DocumentationUrls::page(DocumentationSystem::Wiki, 'sign-in', 'limits')` (null poza strefą, gdy stref z ekranami jest kilka).
