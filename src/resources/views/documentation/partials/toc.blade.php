{{-- Kolumna 3: spis treści strony ($headings — nagłówki h2 i h3). --}}
<nav class="pg-doc-toc" aria-label="{{ text('polygon::documentation.toc') }}" data-pg-doc-toc>
    <p class="pg-doc__group">{{ text('polygon::documentation.toc') }}</p>
    <ul class="pg-doc__list">
        @foreach ($headings as $heading)
            <li @class(['pg-doc-toc__sub' => $heading->level === 3])><a href="#{{ $heading->id }}">{{ $heading->text }}</a></li>
        @endforeach
    </ul>
</nav>
