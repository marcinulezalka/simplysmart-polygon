{{-- Dokument ramki przykładu Preview (Documentation\PreviewExample): $content — wyrenderowany kod, $assets — paczka => wejścia, $theme, $fontSize, $title. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ $theme->value }}" data-font-size="{{ $fontSize->value }}">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <base target="_top">
    @foreach ($assets as $package => $entries)
        @packageAssets($package, $entries)
    @endforeach
</head>
<body>
{!! $content !!}
</body>
</html>
