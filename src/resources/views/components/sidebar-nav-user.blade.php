{{-- Pozycja użytkownika w <x-polygon::sidebar-nav-icon.group>: zamiast ikony <x-polygon::avatar> (`.pg-sidebar-nav-user*`
w resources/scss/polygon/components.scss, podgląd w preview/sidebar-nav-user.md). Props: $href, $label (np. nazwa konta), $initials, $photo,
$color — jak w avatarze, $current; atrybuty przechodzą na <a>. --}}
@props(['href', 'label', 'initials', 'photo' => null, 'color' => null, 'current' => false])
<x-polygon::sidebar-nav-icon.item {{ $attributes->class(['pg-sidebar-nav-user']) }} :href="$href" :label="$label" :current="$current">
    <x-slot:tile>
        <x-polygon::avatar :initials="$initials" :photo="$photo" :color="$color" />
    </x-slot:tile>
</x-polygon::sidebar-nav-icon.item>
