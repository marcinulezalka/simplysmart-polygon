{{-- Avatar użytkownika w treści strony — kształt kafelka ikony nawigacji (<x-polygon::sidebar-nav-user> rysuje nim swoją pozycję): zdjęcie albo
inicjały, ramka w kolorze użytkownika (`.pg-avatar*` w resources/scss/polygon/components.scss, podgląd w preview/avatar.md). Skąd dane,
decyduje strefa. Rozmiar klasą `size-{wartość}` (6, 8, 10, 12, 14, 16, 20, 24; wartość × 0.25rem, bez niej `size-8`). Props: $initials, $photo — adres zdjęcia albo null, $color — `#RRGGBB` (brak albo inna wartość: kolor akcentu),
$label — nazwa użytkownika dla czytnika ekranu, gdy nie stoi obok (bez niej avatar jest ozdobny); atrybuty przechodzą na kontener. --}}
@props(['initials', 'photo' => null, 'color' => null, 'label' => null])
@php($avatarColor = is_string($color) && preg_match('/^#[0-9a-f]{6}$/iD', $color) === 1 ? $color : null)
<span {{ $attributes->class(['pg-avatar'])->merge($avatarColor !== null ? ['style' => "--pg-avatar-color: {$avatarColor}"] : [])->merge($label !== null ? ['role' => 'img', 'aria-label' => $label] : ['aria-hidden' => 'true']) }}>
    @if ($photo !== null)
        <img class="pg-avatar__photo" src="{{ $photo }}" alt="">
    @else
        {{ $initials }}
    @endif
</span>
