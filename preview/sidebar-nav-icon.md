# Nawigacja z ikon

- [Nawigacja](#nav)
- [Grupy](#groups)
- [Przesunięcie zaznaczenia](#action-slide)
- [Zasady](#rules)

Menu boczne zwężone do samych ikon: `<x-polygon::sidebar-nav-icon>`. Domyślnie stoi w połowie wysokości ekranu i nie przewija się
ze stroną; `:centered="false"` zostawia je w miejscu układu strony.

<a name="nav"></a>
## Nawigacja

Nazwa pozycji jest w dymku i dla czytnika ekranu. Bieżąca pozycja ma ikonę w kolorze akcentu i pierścień, najechanie — pierścień i tło.

```blade preview
@php($icon = static fn (string $name) => new \Illuminate\Support\HtmlString(\Simplysmart\Polygon\Icons\LocalIconSets::get('lucide')?->svg($name) ?? ''))
<div class="pg-demo__stage pg-demo__stage--tall">
<x-polygon::sidebar-nav-icon label="Menu">
    <x-polygon::sidebar-nav-icon.group>
        <x-polygon::sidebar-nav-icon.item href="#nav" label="Pulpit" :current="true">{{ $icon('layout-dashboard') }}</x-polygon::sidebar-nav-icon.item>
        <x-polygon::sidebar-nav-icon.item href="#nav" label="Kalendarz">{{ $icon('calendar') }}</x-polygon::sidebar-nav-icon.item>
        <x-polygon::sidebar-nav-icon.item href="#nav" label="Wyszukiwarka">{{ $icon('search') }}</x-polygon::sidebar-nav-icon.item>
    </x-polygon::sidebar-nav-icon.group>
</x-polygon::sidebar-nav-icon>
</div>
```

<a name="groups"></a>
## Grupy

Każda grupa to osobny panel z nazwą dla czytnika ekranu; odstęp między nimi jest równy nagłówkowi i stopce karty.

```blade preview
@php($icon = static fn (string $name) => new \Illuminate\Support\HtmlString(\Simplysmart\Polygon\Icons\LocalIconSets::get('lucide')?->svg($name) ?? ''))
<div class="pg-demo__stage pg-demo__stage--tall">
<x-polygon::sidebar-nav-icon label="Menu">
    <x-polygon::sidebar-nav-icon.group label="Praca">
        <x-polygon::sidebar-nav-icon.item href="#groups" label="Pulpit">{{ $icon('layout-dashboard') }}</x-polygon::sidebar-nav-icon.item>
        <x-polygon::sidebar-nav-icon.item href="#groups" label="Kalendarz" :current="true">{{ $icon('calendar') }}</x-polygon::sidebar-nav-icon.item>
    </x-polygon::sidebar-nav-icon.group>
    <x-polygon::sidebar-nav-icon.group label="Konto">
        <x-polygon::sidebar-nav-icon.item href="#groups" label="Ustawienia">{{ $icon('settings') }}</x-polygon::sidebar-nav-icon.item>
    </x-polygon::sidebar-nav-icon.group>
</x-polygon::sidebar-nav-icon>
</div>
```

<a name="action-slide"></a>
## Przesunięcie zaznaczenia

`action-slide` działa jak przełącznik: po kliknięciu zaznaczenie przesuwa się do ikony z ikony zaznaczonej w tym samym panelu. Panel bez
zaznaczenia albo z jedną ikoną — samo zaznaczenie, tak samo przy ograniczonym ruchu w systemie. Zaznaczona jest jedna ikona w całej
nawigacji. Strefa wywołuje `initSidebarNavIcon()` (`resources/js/sidebar-nav-icon.js`) we własnym wejściu JS.

```blade preview
@php($icon = static fn (string $name) => new \Illuminate\Support\HtmlString(\Simplysmart\Polygon\Icons\LocalIconSets::get('lucide')?->svg($name) ?? ''))
<div class="pg-demo__stage pg-demo__stage--tall">
<x-polygon::sidebar-nav-icon label="Menu" action-slide>
    <x-polygon::sidebar-nav-icon.group label="Praca">
        <x-polygon::sidebar-nav-icon.item href="#action-slide" label="Pulpit" :current="true">{{ $icon('layout-dashboard') }}</x-polygon::sidebar-nav-icon.item>
        <x-polygon::sidebar-nav-icon.item href="#action-slide" label="Kalendarz">{{ $icon('calendar') }}</x-polygon::sidebar-nav-icon.item>
        <x-polygon::sidebar-nav-icon.item href="#action-slide" label="Wyszukiwarka">{{ $icon('search') }}</x-polygon::sidebar-nav-icon.item>
    </x-polygon::sidebar-nav-icon.group>
    <x-polygon::sidebar-nav-icon.group label="Konto">
        <x-polygon::sidebar-nav-icon.item href="#action-slide" label="Ustawienia">{{ $icon('settings') }}</x-polygon::sidebar-nav-icon.item>
    </x-polygon::sidebar-nav-icon.group>
</x-polygon::sidebar-nav-icon>
</div>
```

<a name="rules"></a>
## Zasady

| Element | Wartość |
| --- | --- |
| Karta | tło `--pg-sunken`, ramka od `--pg-border` do `--pg-border-fade`, cień `--pg-shadow-item` |
| Panel grupy | tło `--pg-surface`, linie `--pg-border` u góry i u dołu, co najmniej 3.5rem wysokości (także z jedną ikoną) |
| Pozycja | `--pg-text`, najechanie `--pg-sunken`, bieżąca `--pg-accent` |
| Kafelek ikony | pierścień `--pg-ring`; bieżący na tle `--pg-accent-bg`, najechany na `--pg-surface` |
| Zaokrąglenia | pozycja `--pg-radius-card`, kafelek o 0.25rem mniej, panel i karta o 0.5rem więcej |
