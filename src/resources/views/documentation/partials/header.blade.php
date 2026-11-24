{{-- Nagłówek: przycisk spisu (wąski ekran), zakładki systemów ($tabs), tryb koloru i rozmiar tekstu (wymagają JS, więc ukryte bez niego). --}}
<header class="pg-doc__header">
    <button class="pg-doc__menu" type="button" data-pg-doc-menu aria-controls="pg-doc-index" aria-expanded="false" hidden>{{ text('polygon::documentation.index') }}</button>
    <span class="pg-doc__brand">{{ config('app.name') }}</span>
    <nav class="pg-doc__tabs" aria-label="{{ text('polygon::documentation.tabs') }}">
        @foreach (\Simplysmart\Polygon\Documentation\DocumentationSystem::cases() as $tab)
            <a class="pg-doc__tab" href="{{ $tabs[$tab->value] }}" @if ($tab === $system) aria-current="true" @endif>{{ $tab->label() }}</a>
        @endforeach
    </nav>
    <div class="pg-doc__settings" data-pg-doc-settings hidden>
        <label>
            <span class="pg-doc__hidden">{{ text('polygon::documentation.theme') }}</span>
            <select data-pg-doc-preference="theme" data-pg-doc-cookie="{{ \Simplysmart\Polygon\View\ColorThemePreference::COOKIE }}">
                @foreach (\Simplysmart\Polygon\Enums\ColorTheme::cases() as $option)
                    <option value="{{ $option->value }}" @selected($option === $themePreference->current())>{{ $option->label() }}</option>
                @endforeach
            </select>
        </label>
        <label>
            <span class="pg-doc__hidden">{{ text('polygon::documentation.font_size') }}</span>
            <select data-pg-doc-preference="fontSize" data-pg-doc-cookie="{{ \Simplysmart\Polygon\View\FontSizePreference::COOKIE }}">
                @foreach (\Simplysmart\Polygon\Enums\FontSize::cases() as $option)
                    <option value="{{ $option->value }}" @selected($option === $fontSizePreference->current())>{{ $option->label() }}</option>
                @endforeach
            </select>
        </label>
    </div>
</header>
