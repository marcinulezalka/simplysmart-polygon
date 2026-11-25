{{-- Grupa pozycji <x-polygon::sidebar-nav-icon>: jasny panel. Props: $label — nazwa grupy dla czytnika ekranu (na ekranie jej nie ma;
zbędna, kiedy grupa jest jedna); atrybuty przechodzą na kontener. --}}
@props(['label' => null])
<div {{ $attributes->class(['pg-sidebar-nav-icon__group'])->merge($label !== null ? ['role' => 'group', 'aria-label' => $label] : []) }}>
    {{ $slot }}
</div>
