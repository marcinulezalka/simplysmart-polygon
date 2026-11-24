{{-- Kolumna 1: spis bazowy systemu ($groups). --}}
<nav aria-label="{{ text('polygon::documentation.index') }}">
    @foreach ($groups as $group)
        <p class="pg-doc__group">{{ $group['title'] }}</p>
        <ul class="pg-doc__list">
            @foreach ($group['items'] as $item)
                <li><a href="{{ $item['url'] }}" @if ($item['current']) aria-current="page" @endif>{{ $item['title'] }}</a></li>
            @endforeach
        </ul>
    @endforeach
</nav>
