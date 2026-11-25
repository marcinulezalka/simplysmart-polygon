{{-- Pozycja <x-polygon::sidebar-nav-icon>: link z ikoną w kafelku; nazwa w dymku i dla czytnika ekranu. Props: $href, $label, $current
— bieżąca strona (`aria-current="page"`, po nim maluje arkusz); slot — ikona ozdobna (`<svg>` albo `<iconify-icon>` z `aria-hidden`);
slot `tile` — własny kafelek zamiast ikony (np. avatar z <x-polygon::sidebar-nav-user>); atrybuty przechodzą na <a>. --}}
@props(['href', 'label', 'current' => false])
<a {{ $attributes->class(['pg-sidebar-nav-icon__item'])->merge(['href' => $href, 'title' => $label, 'aria-current' => $current ? 'page' : null]) }}>
    @isset($tile)
        {{ $tile }}
    @else
        <span class="pg-sidebar-nav-icon__icon" aria-hidden="true">{{ $slot }}</span>
    @endisset
    <span class="pg-sidebar-nav-icon__label">{{ $label }}</span>
</a>
