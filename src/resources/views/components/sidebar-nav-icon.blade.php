{{-- Pionowa nawigacja z samych ikon (menu boczne zwężone do ikon) — komponent motywu Polygon: układ i malowanie w
resources/scss/polygon/components.scss (`.pg-sidebar-nav-icon*`), podgląd w preview/sidebar-nav-icon.md. W środku grupy
<x-polygon::sidebar-nav-icon.group> z pozycjami <x-polygon::sidebar-nav-icon.item>. Karta stoi domyślnie w połowie wysokości ekranu
i nie przewija się ze stroną, a <nav> trzyma jej szerokość w układzie strony. Props: $label — nazwa nawigacji dla czytnika ekranu;
$centered — `false` zostawia kartę tam, gdzie stoi <nav>; $actionSlide — klasa `--action-slide`: po kliknięciu zaznaczenie przesuwa
się do ikony jak w przełączniku (resources/js/sidebar-nav-icon.js, `initSidebarNavIcon()` z wejścia JS strefy); atrybuty przechodzą na
<nav>. --}}
@props(['label', 'centered' => true, 'actionSlide' => false])
<nav {{ $attributes->class(['pg-sidebar-nav-icon', 'pg-sidebar-nav-icon--centered' => $centered, 'pg-sidebar-nav-icon--action-slide' => $actionSlide])->merge(['aria-label' => $label, 'data-pg-sidebar-nav-icon-slide' => $actionSlide]) }}>
    <div class="pg-sidebar-nav-icon__card">
        {{ $slot }}
    </div>
</nav>
