{{-- Przycisk „dalej” formularza logowania: na całą szerokość kolumny, z animowaną strzałką line-md:arrow-small-right (lokalna kolekcja
resources/icons/line-md.json, rysuje się co 4 s), ukryty przy otwartej klawiaturze PIN-u (`data-pg-pinpad-submit`). Slot:
etykieta; atrybuty przechodzą na <button>. --}}
@php($submitIcons = \Simplysmart\Polygon\Icons\IconifyCollection::load(\Simplysmart\Polygon\Providers\PolygonServiceProvider::ROOT . '/resources/icons/line-md.json'))
<button {{ $attributes->class(['pg-button', 'pg-button--soft', 'pg-button--block'])->merge(['type' => 'submit', 'data-pg-pinpad-submit' => true]) }}>{{ $slot }}{!! $submitIcons->svg('arrow-small-right', ['class' => 'pg-sign-in__arrow', 'aria-hidden' => 'true', 'focusable' => 'false']) !!}</button>
