# Motyw Polygon

- [Kolory](#colors)
- [Tokeny wspólne](#shared)
- [Użycie](#usage)

Motyw strefy `polygon`: tokeny `--pg-*` i komponenty `.pg-*`. Używa go ta dokumentacja. Strefa z własnym motywem go nie ładuje. Opis: [Motyw Polygon](../docs/assety.md#motyw-polygon).

<a name="colors"></a>
## Kolory

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

<a name="shared"></a>
## Tokeny wspólne

```blade preview
@inject('tokens', \Simplysmart\Polygon\Theme\ThemeTokens::class)
<div class="pg-demo__scroll">
    <table class="pg-table">
        <thead><tr><th>Token</th><th>Wartość</th></tr></thead>
        <tbody>
            @foreach ($tokens->base() as $token => $value)
                <tr><td class="pg-demo__mono">{{ $token }}</td><td class="pg-demo__mono">{{ $value }}</td></tr>
            @endforeach
        </tbody>
    </table>
</div>
```

<a name="usage"></a>
## Użycie

Widok ładuje układ komponentów polygonu i motyw:

```blade
@packageAssets('polygon', \Simplysmart\Polygon\Theme\ThemeAssets::ENTRIES)
```
