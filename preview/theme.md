# Motyw w całości

- [Całość](#overview)
- [Użycie](#usage)

Podstawy i komponenty złożone w jeden ekran. Opis: [Motyw Polygon](../docs/assety.md#motyw-polygon).

<a name="overview"></a>
## Całość

Komponenty motywu razem, na jednym ekranie:

```blade preview
<section class="pg-card">
    <h2 class="pg-card__title">Faktury</h2>
    <p class="pg-card__text">Ostatnio wystawione dokumenty.</p>
    <table class="pg-table">
        <thead><tr><th>Numer</th><th>Kontrahent</th><th>Stan</th></tr></thead>
        <tbody>
            <tr><td>FV/1</td><td>Sklep A</td><td><span class="pg-badge pg-badge--success">Opłacona</span></td></tr>
            <tr><td>FV/2</td><td>Sklep B</td><td><span class="pg-badge pg-badge--warning">Oczekuje</span></td></tr>
            <tr><td>FV/3</td><td>Sklep C</td><td><span class="pg-badge pg-badge--danger">Odrzucona</span></td></tr>
        </tbody>
    </table>
    <div class="pg-card__item">
        <div class="pg-field">
            <label class="pg-field__label" for="note">Notatka</label>
            <input class="pg-input" id="note" aria-describedby="note-hint">
            <p class="pg-field__hint" id="note-hint">Widoczna na fakturze.</p>
        </div>
    </div>
    <div class="pg-card__footer">
        <button class="pg-button" type="button">{!! \Simplysmart\Polygon\Icons\LocalIconSets::get('lucide')?->svg('save', ['class' => 'pg-icon', 'aria-hidden' => 'true']) !!}Zapisz</button>
        <button class="pg-button pg-button--secondary" type="button">{!! \Simplysmart\Polygon\Icons\LocalIconSets::get('lucide')?->svg('x', ['class' => 'pg-icon', 'aria-hidden' => 'true']) !!}Anuluj</button>
        <button class="pg-button pg-button--danger" type="button">{!! \Simplysmart\Polygon\Icons\LocalIconSets::get('lucide')?->svg('trash-2', ['class' => 'pg-icon', 'aria-hidden' => 'true']) !!}Usuń</button>
    </div>
</section>
<div class="pg-toasts pg-toasts--static">
    <div class="pg-toast pg-toast--status"><div class="pg-toast__body">Zapisano zmiany.</div><button class="pg-toast__close" type="button" aria-label="Zamknij">×</button></div>
</div>
```

<a name="usage"></a>
## Użycie

Widok ładuje układ komponentów polygonu i motyw:

```blade
@packageAssets('polygon', \Simplysmart\Polygon\Theme\ThemeAssets::ENTRIES)
```
