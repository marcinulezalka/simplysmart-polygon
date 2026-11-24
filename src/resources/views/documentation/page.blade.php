{{-- Strona dokumentacji: treść, zwijany spis treści (wąski ekran) i sąsiednie strony. Zmienne: Documentation\DocumentationScreen::data(). --}}
@extends('polygon::documentation.layout')

@section('content')
    @if ($headings !== [])
        <details class="pg-doc__toc-inline">
            <summary>{{ text('polygon::documentation.toc') }}</summary>
            @include('polygon::documentation.partials.toc')
        </details>
    @endif
    <article class="pg-doc-content" lang="pl">
        {!! $html !!}
    </article>
    @if ($previous !== null || $next !== null)
        <nav class="pg-doc__pager" aria-label="{{ text('polygon::documentation.pager') }}">
            @if ($previous !== null)
                <a href="{{ $previous['url'] }}" rel="prev"><span>{{ text('polygon::documentation.previous') }}</span>{{ $previous['title'] }}</a>
            @endif
            @if ($next !== null)
                <a class="pg-doc__pager-next" href="{{ $next['url'] }}" rel="next"><span>{{ text('polygon::documentation.next') }}</span>{{ $next['title'] }}</a>
            @endif
        </nav>
    @endif
@endsection
