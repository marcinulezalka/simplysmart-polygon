# Typografia

- [Kroje](#families)
- [Hierarchia](#hierarchy)
- [Rozmiar tekstu](#sizes)
- [Tokeny](#tokens)

Motyw używa krojów systemowych; kroje stref są hostowane w ich paczkach.

<a name="families"></a>
## Kroje

```blade preview
<p>Tekst: zażółć gęślą jaźń 0123456789</p>
<p class="pg-demo__mono">Kod: zażółć gęślą jaźń 0123456789</p>
```

<a name="hierarchy"></a>
## Hierarchia

```blade preview
<h1>Nagłówek 1</h1>
<h2>Nagłówek 2</h2>
<h3>Nagłówek 3</h3>
<p class="pg-demo__muted">Tekst pomocniczy</p>
```

<a name="sizes"></a>
## Rozmiar tekstu

Trzy rozmiary: `a`, `aa` i `aaa`. Skalują wszystkie wymiary w `rem`. Mechanizm: [Rozmiar czcionki](../docs/assety.md#rozmiar-czcionki).

```blade preview
<p>Zmień rozmiar tekstu w nagłówku strony.</p>
```

<a name="tokens"></a>
## Tokeny

```blade preview
@inject('tokens', \Simplysmart\Polygon\Theme\ThemeTokens::class)
<div class="pg-demo__scroll">
    <table class="pg-table">
        <thead><tr><th>Token</th><th>Wartość</th></tr></thead>
        <tbody>
            @foreach ($tokens->base() as $token => $value)
                @continue(str_contains($token, 'radius'))
                <tr><td class="pg-demo__mono">{{ $token }}</td><td class="pg-demo__mono">{{ $value }}</td></tr>
            @endforeach
        </tbody>
    </table>
</div>
```
