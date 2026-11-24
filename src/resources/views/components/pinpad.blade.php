{{-- Klawiatura numeryczna pola PIN-u — komponent bazowy dla każdej strefy (docs/strefy.md → „Klawiatura numeryczna PIN-u”):
klawisze 0–9, „C” (czyści całe pole) i „OK” (zwykły przycisk wysyłający formularz). Sam układ i znaczniki: pole, przycisk
`[data-pg-pinpad-toggle]` i korzeń `[data-pg-pinpad]` wokół nich daje strefa, zachowanie — resources/js/pinpad.js. Ukryta,
dopóki strefa jej nie otworzy (bez JS zostaje zwykłe pole). Props: $id (dla `aria-controls` przycisku); atrybuty przechodzą
na kontener. --}}
@props(['id'])
<div {{ $attributes->class(['pg-pinpad'])->merge(['id' => $id, 'role' => 'group', 'aria-label' => text('polygon::pinpad.label')]) }} data-pg-pinpad-keys hidden>
    @foreach ([1, 2, 3, 4, 5, 6, 7, 8, 9] as $digit)
        <button class="pg-pinpad__key" type="button" data-pg-pinpad-digit="{{ $digit }}">{{ $digit }}</button>
    @endforeach
    <button class="pg-pinpad__key" type="button" aria-label="{{ text('polygon::pinpad.clear') }}" title="{{ text('polygon::pinpad.clear') }}" data-pg-pinpad-clear>C</button>
    <button class="pg-pinpad__key" type="button" data-pg-pinpad-digit="0">0</button>
    <button class="pg-pinpad__key pg-pinpad__key--submit" type="submit">{{ text('polygon::pinpad.submit') }}</button>
</div>
