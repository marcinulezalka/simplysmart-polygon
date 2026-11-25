{{-- Pole formularza logowania: etykieta, pole z opcjonalną ikoną z lewej (slot `icon`) i przyciskiem z prawej (slot domyślny, klasa
`.pg-sign-in__action`, np. klawiatura PIN-u). Błąd walidacji pola $name: obrys i ikona w polu z prawej, komunikat na środku pod polem.
Props: $id, $name, $label, $error (komunikat zamiast szukanego w $errors); pozostałe atrybuty przechodzą na <input>. --}}
@props(['id', 'name', 'label', 'error' => null])
@php($fieldError = $error ?? ($errors ?? new \Illuminate\Support\ViewErrorBag())->first($name))
<div class="pg-sign-in__field">
    <label class="pg-sign-in__label" for="{{ $id }}">{{ $label }}</label>
    <div @class(['pg-sign-in__control', 'pg-sign-in__control--icon' => isset($icon), 'pg-sign-in__control--invalid' => $fieldError !== ''])>
        @isset($icon)
            <span class="pg-sign-in__icon" aria-hidden="true">{{ $icon }}</span>
        @endisset
        <input {{ $attributes->class(['pg-input'])->merge(['type' => 'text', 'id' => $id, 'name' => $name, ...($fieldError !== '' ? ['aria-invalid' => 'true', 'aria-describedby' => $id . '-error'] : [])]) }}>
        @if ($fieldError !== '')
            <span class="pg-sign-in__alert" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3M12 9v4m0 4h.01" /></svg></span>
        @endif
        {{ $slot }}
    </div>
    @if ($fieldError !== '')
        <p class="pg-sign-in__error" id="{{ $id }}-error" role="alert">{{ $fieldError }}</p>
    @endif
</div>
