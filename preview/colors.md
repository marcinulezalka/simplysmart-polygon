# Kolory

- [Warstwy tła i tekst](#surfaces)
- [Kolory znaczeniowe](#meaning)
- [Role tokenów](#roles)
- [Wartości tokenów](#tokens)
- [Tryby kolorystyczne](#modes)
- [Kontrast](#contrast)

Kolory tylko z tokenów `--pg-*`. Każdy token ma wartość w każdym trybie, więc komponent nie ma własnych reguł trybu. Zmień tryb w nagłówku strony, a przykłady przełączą się razem z nią.

<a name="surfaces"></a>
## Warstwy tła i tekst

Górny pasek jest jaśniejszy od treści, karty najjaśniejsze, a wyróżnienia w karcie ciemniejsze.

```blade preview
<div class="pg-demo">
    <div class="pg-demo__swatch pg-demo__swatch--bg"><span class="pg-demo__label">--pg-bg</span></div>
    <div class="pg-demo__swatch pg-demo__swatch--bar"><span class="pg-demo__label">--pg-bar</span></div>
    <div class="pg-demo__swatch pg-demo__swatch--surface"><span class="pg-demo__label">--pg-surface</span></div>
    <div class="pg-demo__swatch pg-demo__swatch--sunken"><span class="pg-demo__label">--pg-sunken</span></div>
    <div class="pg-demo__swatch pg-demo__swatch--text"><span class="pg-demo__label">--pg-text</span></div>
</div>
```

<a name="meaning"></a>
## Kolory znaczeniowe

Znaczenie zawsze także w tekście albo ikonie, nie samym kolorem.

```blade preview
<div class="pg-demo">
    <div class="pg-demo__swatch pg-demo__swatch--accent"><span class="pg-demo__label">--pg-accent</span></div>
    <div class="pg-demo__swatch pg-demo__swatch--info"><span class="pg-demo__label">--pg-info</span></div>
    <div class="pg-demo__swatch pg-demo__swatch--success"><span class="pg-demo__label">--pg-success</span></div>
    <div class="pg-demo__swatch pg-demo__swatch--warning"><span class="pg-demo__label">--pg-warning</span></div>
    <div class="pg-demo__swatch pg-demo__swatch--danger"><span class="pg-demo__label">--pg-danger</span></div>
</div>
```

<a name="roles"></a>
## Role tokenów

| Token | Rola |
| --- | --- |
| `--pg-bg` | tło treści strony |
| `--pg-bar` | górny pasek |
| `--pg-surface` | karty, pola, panele |
| `--pg-sunken` | wyróżnienia: kod, nagłówki tabel, najechanie |
| `--pg-text` | tekst |
| `--pg-title` | nagłówki i tytuły kart |
| `--pg-muted` | tekst pomocniczy |
| `--pg-border` | linie i ramki kart |
| `--pg-control` | ramki pól i przycisków |
| `--pg-accent`, `--pg-accent-hover` | główna akcja, linki, bieżąca pozycja i ich najechanie |
| `--pg-accent-bg` | tło bieżącej pozycji |
| `--pg-on-accent` | tekst na akcencie i na przycisku usuwania |
| `--pg-focus` | obrys fokusu |
| `--pg-info`, `--pg-info-bg` | informacja i jej tło |
| `--pg-success`, `--pg-success-bg` | powodzenie i jego tło |
| `--pg-warning`, `--pg-warning-bg` | ostrzeżenie i jego tło |
| `--pg-danger`, `--pg-danger-bg` | błąd, usuwanie i ich tło |

<a name="tokens"></a>
## Wartości tokenów

```blade preview
@inject('tokens', \Simplysmart\Polygon\Theme\ThemeTokens::class)
<div class="pg-demo__scroll">
    <table class="pg-table">
        <thead><tr><th>Token</th><th>Teraz</th>@foreach ($tokens->modes() as $mode)<th>{{ $mode->label() }}</th>@endforeach</tr></thead>
        <tbody>
            @foreach ($tokens->of(\Simplysmart\Polygon\Enums\ColorTheme::Light) as $token => $value)
                @continue(str_contains($token, 'shadow'))
                <tr>
                    <td class="pg-demo__mono">{{ $token }}</td>
                    <td><span class="pg-demo__token" style="background: var({{ $token }})"></span></td>
                    @foreach ($tokens->modes() as $mode)<td class="pg-demo__mono">{{ $tokens->of($mode)[$token] }}</td>@endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
```

<a name="modes"></a>
## Tryby kolorystyczne

Mechanizm: [Tryby kolorystyczne](../docs/assety.md#tryby-kolorystyczne).

| Tryb | Kiedy |
| --- | --- |
| `auto` | według systemu: jasny, ciemny albo duży kontrast |
| `light` | jasny |
| `dark` | ciemny |
| `wcag` | jasny, o kontraście WCAG AA |
| `wcag-contrast` | biel na czerni, o kontraście WCAG AAA |

<a name="contrast"></a>
## Kontrast

Wymagania obowiązują w trybach `wcag` i `wcag-contrast`; pilnuje ich test motywu.

| Element | `wcag` | `wcag-contrast` |
| --- | --- | --- |
| Tekst | 4.5:1 | 7:1 |
| Ramki kontrolek i obrys fokusu | 3:1 | 3:1 |
