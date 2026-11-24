{{-- Layout dokumentacji (Wiki, Docs, Preview): nagłówek z zakładkami systemów i trzy kolumny — spis bazowy, treść, spis treści strony. Zmienne: Documentation\DocumentationScreen::data(). --}}
@inject('themePreference', \Simplysmart\Polygon\View\ColorThemePreference::class)
@inject('fontSizePreference', \Simplysmart\Polygon\View\FontSizePreference::class)
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ $themePreference->current()->value }}" data-font-size="{{ $fontSizePreference->current()->value }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title }} — {{ $system->label() }} — {{ config('app.name') }}</title>
    @packageAssets('polygon', \Simplysmart\Polygon\Documentation\DocumentationAssets::ENTRIES)
</head>
<body class="pg-doc" data-pg-doc-copy="{{ text('polygon::documentation.copy') }}" data-pg-doc-copied="{{ text('polygon::documentation.copied') }}">
    <a class="pg-doc__skip" href="#pg-doc-content">{{ text('polygon::documentation.skip') }}</a>
    @include('polygon::documentation.partials.header')
    <div class="pg-doc__columns">
        <aside class="pg-doc__index" id="pg-doc-index">
            @include('polygon::documentation.partials.index')
        </aside>
        <main class="pg-doc__main" id="pg-doc-content" tabindex="-1">
            @yield('content')
        </main>
        <aside class="pg-doc__toc">
            @if ($headings !== [])
                @include('polygon::documentation.partials.toc')
            @endif
        </aside>
    </div>
</body>
</html>
