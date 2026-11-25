# Zaokrąglenia i cień

- [Zaokrąglenia](#radius)
- [Cień](#shadow)
- [Tokeny zaokrągleń](#radius-tokens)
- [Tokeny cienia](#shadow-tokens)

Promienie i cienie tylko z tokenów; w trybach WCAG cieni nie ma.

<a name="radius"></a>
## Zaokrąglenia

```blade preview
<div class="pg-demo">
    <div class="pg-demo__box pg-demo__box--sm">--pg-radius-sm</div>
    <div class="pg-demo__box pg-demo__box--md">--pg-radius</div>
    <div class="pg-demo__box pg-demo__box--lg">--pg-radius-card</div>
</div>
```

<a name="shadow"></a>
## Cień

```blade preview
<div class="pg-demo">
    <div class="pg-demo__box pg-demo__box--md pg-demo__box--shadow">--pg-shadow</div>
    <div class="pg-demo__box pg-demo__box--sm pg-demo__box--shadow-item">--pg-shadow-item</div>
</div>
```

<a name="radius-tokens"></a>
## Tokeny zaokrągleń

```blade preview
@inject('tokens', \Simplysmart\Polygon\Theme\ThemeTokens::class)
<div class="pg-demo__scroll">
    <table class="pg-table">
        <thead><tr><th>Token</th><th>Wartość</th></tr></thead>
        <tbody>
            @foreach ($tokens->base() as $token => $value)
                @continue(! str_contains($token, 'radius'))
                <tr><td class="pg-demo__mono">{{ $token }}</td><td class="pg-demo__mono">{{ $value }}</td></tr>
            @endforeach
        </tbody>
    </table>
</div>
```

<a name="shadow-tokens"></a>
## Tokeny cienia

```blade preview
@inject('tokens', \Simplysmart\Polygon\Theme\ThemeTokens::class)
<div class="pg-demo__scroll">
    <table class="pg-table">
        <thead><tr><th>Token</th>@foreach ($tokens->modes() as $mode)<th>{{ $mode->label() }}</th>@endforeach</tr></thead>
        <tbody>
            @foreach ($tokens->of(\Simplysmart\Polygon\Enums\ColorTheme::Light) as $token => $value)
                @continue(! str_contains($token, 'shadow'))
                <tr>
                    <td class="pg-demo__mono">{{ $token }}</td>
                    @foreach ($tokens->modes() as $mode)<td class="pg-demo__mono">{{ $tokens->of($mode)[$token] }}</td>@endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
```
