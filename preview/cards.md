# Karty

- [Karta](#card)
- [Karta w karcie](#nested)

Cień z tokenu; karta w karcie ma mniejszy promień i własny cień. Zasady: [Bazowe zasady UI stref](../docs/assety.md#bazowe-zasady-ui-stref).

<a name="card"></a>
## Karta

```blade preview
<section class="pg-card">
    <h2 class="pg-card__title">Tytuł karty</h2>
    <p class="pg-card__text">Opis karty.</p>
    <div class="pg-card__footer">
        <button class="pg-button" type="button">{!! \Simplysmart\Polygon\Icons\LocalIconSets::get('lucide')?->svg('check', ['class' => 'pg-icon', 'aria-hidden' => 'true']) !!}Potwierdź</button>
    </div>
</section>
```

<a name="nested"></a>
## Karta w karcie

```blade preview
<section class="pg-card">
    <h2 class="pg-card__title">Lista</h2>
    <div class="pg-card__item">Pierwsza pozycja</div>
    <div class="pg-card__item">Druga pozycja</div>
</section>
```
