# Ikony

- [Zestawy lokalne](#local)

Ikony Iconify `zestaw:ikona`. Mechanizm: [Ikony](../docs/ikony.md).

<a name="local"></a>
## Zestawy lokalne

```blade preview
<div class="pg-demo">
    @foreach (['heart-pulse', 'calendar', 'search', 'settings'] as $name)
        {!! \Simplysmart\Polygon\Icons\LocalIconSets::get('lucide')?->svg($name, ['height' => '32']) !!}
    @endforeach
</div>
```
