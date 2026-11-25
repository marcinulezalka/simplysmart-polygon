# Avatar

- [W treści](#content)
- [Rozmiary](#sizes)
- [Na liście](#list)
- [Dostępność](#accessibility)
- [Zasady](#rules)

`<x-polygon::avatar>` to klasyczny avatar użytkownika w treści strony: zdjęcie albo inicjały, w kształcie kafelka ikony
[nawigacji](sidebar-nav-icon.md). Tym samym avatarem rysuje się [użytkownik w nawigacji](sidebar-nav-user.md).

<a name="content"></a>
## W treści

Ramka ma grubość pierścienia ikony i kolor użytkownika z przezroczystością. Avatar stoi przed nazwą, razem w `.pg-avatar-name`.

```blade preview
@php($photo = static fn (int $number): string => asset('vendor/polygon/preview/avatars/avatar-' . $number . '.jpg'))
<div class="pg-demo">
    <span class="pg-avatar-name"><x-polygon::avatar initials="AN" :photo="$photo(1)" color="#B45309" />Anna Nowak</span>
    <span class="pg-avatar-name"><x-polygon::avatar initials="PW" :photo="$photo(2)" color="#1D4ED8" />Piotr Wiśniewski</span>
    <span class="pg-avatar-name"><x-polygon::avatar initials="JK" color="#7C3AED" />Jan Kowalski</span>
    <span class="pg-avatar-name"><x-polygon::avatar initials="EZ" />Ewa Zielińska</span>
</div>
```

<a name="sizes"></a>
## Rozmiary

Rozmiar daje klasa `size-{wartość}`: 6, 8 (domyślny), 10, 12, 14, 16, 20 albo 24, czyli wartość × 0.25rem. Zaokrąglenie i inicjały rosną
razem z rozmiarem, ramka ma stałą grubość.

```blade preview
@php($photo = asset('vendor/polygon/preview/avatars/avatar-1.jpg'))
<div class="pg-demo">
    <x-polygon::avatar class="size-6" initials="AN" :photo="$photo" color="#B45309" label="Anna Nowak, size-6" />
    <x-polygon::avatar class="size-8" initials="AN" :photo="$photo" color="#B45309" label="Anna Nowak, size-8" />
    <x-polygon::avatar class="size-10" initials="AN" :photo="$photo" color="#B45309" label="Anna Nowak, size-10" />
    <x-polygon::avatar class="size-12" initials="AN" :photo="$photo" color="#B45309" label="Anna Nowak, size-12" />
    <x-polygon::avatar class="size-14" initials="JK" color="#7C3AED" label="Jan Kowalski, size-14" />
    <x-polygon::avatar class="size-16" initials="JK" color="#7C3AED" label="Jan Kowalski, size-16" />
    <x-polygon::avatar class="size-20" initials="JK" color="#7C3AED" label="Jan Kowalski, size-20" />
    <x-polygon::avatar class="size-24" initials="JK" color="#7C3AED" label="Jan Kowalski, size-24" />
</div>
```

<a name="list"></a>
## Na liście

```blade preview
@php($photo = static fn (int $number): string => asset('vendor/polygon/preview/avatars/avatar-' . $number . '.jpg'))
<table class="pg-table">
    <thead><tr><th>Osoba</th><th>Rola</th></tr></thead>
    <tbody>
        <tr><td><span class="pg-avatar-name"><x-polygon::avatar initials="AN" :photo="$photo(1)" color="#B45309" />Anna Nowak</span></td><td>Kierownik</td></tr>
        <tr><td><span class="pg-avatar-name"><x-polygon::avatar initials="JK" color="#7C3AED" />Jan Kowalski</span></td><td>Księgowość</td></tr>
    </tbody>
</table>
```

<a name="accessibility"></a>
## Dostępność

Avatar obok nazwy jest ozdobny i czytnik ekranu go pomija. Avatar bez nazwy obok dostaje ją w `label`.

```blade preview
<x-polygon::avatar initials="JK" color="#7C3AED" label="Jan Kowalski" />
```

<a name="rules"></a>
## Zasady

| Element | Wartość |
| --- | --- |
| Dane | `initials`, `photo` (adres zdjęcia), `color` (`#RRGGBB`) daje strefa |
| Kształt | kafelek ikony nawigacji: domyślnie 2rem, zaokrąglenie 0.375 rozmiaru; nigdy koło |
| Ramka | grubość `--pg-ring`, kolor użytkownika o kryciu `--pg-ring-opacity` |
| Brak zdjęcia | inicjały w kolorze `--pg-title` na tle w odcieniu koloru użytkownika |
| Brak koloru | `--pg-accent` |
