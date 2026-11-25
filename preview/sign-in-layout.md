# Layout logowania

- [Logowanie](#sign-in)
- [Ekran z błędem](#error)
- [Elementy](#parts)

Obszar logowania: tło, hasło i jedna karta na środku okna. Użycie: [Layout logowania](../docs/strefy.md#layout-logowania).

<a name="sign-in"></a>
## Logowanie

Bez nazwy ekranu nagłówkiem jest hasło.

```blade preview
<x-polygon::sign-in>
    <x-slot:hero><x-polygon::sign-in.hero lead="Dzień dobry" link="zaczyna się od" accent="porządku na biurku." /></x-slot:hero>
    <x-slot:brand>Polygon</x-slot:brand>
    <form onsubmit="return false">
        <x-polygon::sign-in.field id="sign-in-email" name="sign_in_email" type="email" label="Adres e-mail">
            <x-slot:icon>{!! \Simplysmart\Polygon\Icons\LocalIconSets::get('lucide')?->svg('mail') !!}</x-slot:icon>
        </x-polygon::sign-in.field>
        <x-polygon::sign-in.submit>Dalej</x-polygon::sign-in.submit>
    </form>
</x-polygon::sign-in>
```

<a name="error"></a>
## Ekran z błędem

Błąd stoi przy polu: obrys, ikona z prawej i komunikat pod polem.

```blade preview
<x-polygon::sign-in>
    <x-slot:brand>Polygon</x-slot:brand>
    <form onsubmit="return false">
        <x-polygon::sign-in.field id="preview-code" name="preview_code" label="Kod z e-maila" value="123456" inputmode="numeric" error="Kod jest nieprawidłowy." />
        <x-polygon::sign-in.submit>Dalej</x-polygon::sign-in.submit>
    </form>
    <div class="pg-sign-in__links">
        <button class="pg-sign-in__link" type="button">Wyślij kod ponownie</button>
        <a class="pg-sign-in__link" href="#error">Zmień adres e-mail</a>
    </div>
</x-polygon::sign-in>
```

<a name="parts"></a>
## Elementy

| Element | Wartość |
| --- | --- |
| Tło | `--pg-backdrop`, stałe pod treścią; w trybach WCAG go nie ma |
| Hasło | `--pg-on-backdrop` (pierwszy wiersz przechodzi w `--pg-on-backdrop-fade`) |
| Znak | `--pg-mono` i `--pg-title` |
| Przycisk „dalej” | `.pg-button--soft` ze strzałką `line-md:arrow-small-right`, która rysuje się co 4 s |
| Karta | do 30rem szerokości, ramka i paski `--pg-glass` z rozmyciem, treść na `--pg-surface` |
| Zaokrąglenia | karta i treść `--pg-radius-card` plus 0.5rem, pola i przyciski `--pg-radius-card` |
| Kolumna formularza | 70% karty, na wąskim ekranie cała |
