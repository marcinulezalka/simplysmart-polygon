{{-- Layout obszaru logowania w motywie Polygon (logowanie, kod, PIN, reset PIN-u, blokada ekranu) — opcjonalny: używa go strefa bez
własnego motywu (`@extends('polygon::layouts.sign-in')`, docs/strefy.md → „Layout logowania”, podgląd: preview/sign-in-layout.md).
Wygląd karty i tła: <x-polygon::sign-in>. Sekcje: `title` (nazwa ekranu; z `heading-hidden` tylko w zakładce i dla czytnika ekranu),
`intro`, `hero` (hasło nad kartą, np. <x-polygon::sign-in.hero>), `brand` (znak w karcie, domyślnie nazwa aplikacji), `panel` (formularze)
i `error-fields` — pola z błędem przy sobie (`email,code`); pozostałe błędy i komunikat po akcji stoją nad formularzem, więc żaden nie
ginie. Stos `head` — favicon i skrypty strefy. --}}
@inject('themePreference', \Simplysmart\Polygon\View\ColorThemePreference::class)
@inject('fontSizePreference', \Simplysmart\Polygon\View\FontSizePreference::class)
@php
    $signInSection = static fn (string $name): ?\Illuminate\Support\HtmlString => $__env->hasSection($name) ? new \Illuminate\Support\HtmlString(trim($__env->yieldContent($name))) : null;
    $signInFields = explode(',', str_replace(' ', '', $__env->yieldContent('error-fields')));
    $signInErrors = \Illuminate\Support\Arr::flatten(\Illuminate\Support\Arr::except(($errors ?? new \Illuminate\Support\ViewErrorBag())->getBag('default')->getMessages(), $signInFields));
    $signInStatus = session(\Simplysmart\Polygon\Http\StatusMessage::KEY);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ $themePreference->current()->value }}" data-font-size="{{ $fontSizePreference->current()->value }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@hasSection('title')@yield('title') — @endif{{ config('app.name') }}</title>
    @packageAssets('polygon', \Simplysmart\Polygon\Theme\ThemeAssets::SIGN_IN)
    @stack('head')
</head>
<body class="pg-sign-in-page">
    <x-polygon::sign-in :title="$signInSection('title')" :heading-hidden="$__env->hasSection('heading-hidden')" :intro="$signInSection('intro')">
        @hasSection('hero')
            <x-slot:hero>@yield('hero')</x-slot:hero>
        @endif
        @hasSection('brand')
            <x-slot:brand>@yield('brand')</x-slot:brand>
        @endif
        @if (is_string($signInStatus))
            <p class="pg-sign-in__message" role="status">{{ $signInStatus }}</p>
        @endif
        @foreach ($signInErrors as $signInError)
            <p class="pg-sign-in__message pg-sign-in__message--danger" role="alert">{{ $signInError }}</p>
        @endforeach
        @yield('panel')
    </x-polygon::sign-in>
</body>
</html>
