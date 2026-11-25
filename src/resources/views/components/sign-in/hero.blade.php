{{-- Hasło nad kartą logowania (slot `hero` <x-polygon::sign-in>) w trzech stylach: mocne otwarcie, drobny łącznik wersalikami między
kreskami i lekkie domknięcie. Props: $lead, $link, $accent — treść daje strefa. --}}
@props(['lead', 'link' => null, 'accent' => null])
<span class="pg-sign-in__hero-lead">{{ $lead }}</span>
@if ($link !== null)
    <span class="pg-sign-in__hero-link">{{ $link }}</span>
@endif
@if ($accent !== null)
    <span class="pg-sign-in__hero-accent">{{ $accent }}</span>
@endif
