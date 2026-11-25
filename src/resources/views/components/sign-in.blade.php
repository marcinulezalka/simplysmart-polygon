{{-- Ekran obszaru logowania (layouts/sign-in.blade.php; `.pg-sign-in*` w resources/scss/polygon/sign-in.scss): tło, hasło nad kartą i jedna
karta na środku okna — znak, pod nim węższa kolumna: nazwa ekranu, opis i formularze (slot), w równych odstępach. Nagłówkiem
h1 jest nazwa ekranu, a bez niej hasło (slot `hero`) albo znak (slot `brand`, domyślnie nazwa aplikacji). Props: $title, $headingHidden
(nazwa tylko dla czytnika ekranu), $intro; atrybuty przechodzą na <main>. --}}
@props(['title' => null, 'headingHidden' => false, 'intro' => null])
@php($heroHeading = $title === null && isset($hero))
@php($brandHeading = $title === null && ! isset($hero))
<main {{ $attributes->class(['pg-sign-in']) }}>
    @isset($hero)
        <{{ $heroHeading ? 'h1' : 'div' }} class="pg-sign-in__hero" @if ($heroHeading) id="pg-sign-in-heading" @endif>{{ $hero }}</{{ $heroHeading ? 'h1' : 'div' }}>
    @endisset
    <section class="pg-sign-in__card" aria-labelledby="pg-sign-in-heading">
        <div class="pg-sign-in__content">
            <{{ $brandHeading ? 'h1' : 'div' }} class="pg-sign-in__brand" @if ($brandHeading) id="pg-sign-in-heading" @endif>{{ $brand ?? config('app.name') }}</{{ $brandHeading ? 'h1' : 'div' }}>
            <div class="pg-sign-in__panel">
                @if ($title !== null)
                    <h1 id="pg-sign-in-heading" @class(['pg-sign-in__title', 'pg-sign-in__title--hidden' => $headingHidden])>{{ $title }}</h1>
                @endif
                @if ($intro !== null)
                    <p class="pg-sign-in__intro">{{ $intro }}</p>
                @endif
                {{ $slot }}
            </div>
        </div>
    </section>
</main>
