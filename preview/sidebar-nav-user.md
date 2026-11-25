# Użytkownik w nawigacji

- [Avatar](#avatar)

`<x-polygon::sidebar-nav-user>` to pozycja [nawigacji z ikon](sidebar-nav-icon.md), która zamiast ikony ma [avatar](avatar.md): zdjęcie albo inicjały.

<a name="avatar"></a>
## Avatar

Avatar jest ten sam co w treści. Bieżąca pozycja ma drugą ramkę w kolorze użytkownika.

```blade preview
@php($icon = static fn (string $name) => new \Illuminate\Support\HtmlString(\Simplysmart\Polygon\Icons\LocalIconSets::get('lucide')?->svg($name) ?? ''))
@php($photo = static fn (int $number): string => asset('vendor/polygon/preview/avatars/avatar-' . $number . '.jpg'))
<div class="pg-demo__stage pg-demo__stage--tall">
<x-polygon::sidebar-nav-icon label="Menu">
    <x-polygon::sidebar-nav-icon.group label="Praca">
        <x-polygon::sidebar-nav-icon.item href="#avatar" label="Pulpit" :current="true">{{ $icon('layout-dashboard') }}</x-polygon::sidebar-nav-icon.item>
    </x-polygon::sidebar-nav-icon.group>
    <x-polygon::sidebar-nav-icon.group label="Użytkownicy">
        <x-polygon::sidebar-nav-user href="#avatar" label="Anna Nowak" initials="AN" :photo="$photo(1)" color="#B45309" />
        <x-polygon::sidebar-nav-user href="#avatar" label="Piotr Wiśniewski" initials="PW" :photo="$photo(2)" color="#1D4ED8" />
        <x-polygon::sidebar-nav-user href="#avatar" label="Jan Kowalski" initials="JK" color="#7C3AED" />
        <x-polygon::sidebar-nav-user href="#avatar" label="Ewa Zielińska" initials="EZ" />
    </x-polygon::sidebar-nav-icon.group>
</x-polygon::sidebar-nav-icon>
</div>
```
