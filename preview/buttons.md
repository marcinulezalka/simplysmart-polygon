# Przyciski

- [Warianty](#variants)
- [Nieaktywny](#disabled)
- [Pełna szerokość](#block)

Jeden rozmiar, zawsze z ikoną; najechanie zmienia tylko kolor. Zasady: [Bazowe zasady UI stref](../docs/assety.md#bazowe-zasady-ui-stref).

<a name="variants"></a>
## Warianty

```blade preview
<div class="pg-demo">
    <button class="pg-button" type="button">{!! \Simplysmart\Polygon\Icons\LocalIconSets::get('lucide')?->svg('save', ['class' => 'pg-icon', 'aria-hidden' => 'true']) !!}Zapisz</button>
    <button class="pg-button pg-button--secondary" type="button">{!! \Simplysmart\Polygon\Icons\LocalIconSets::get('lucide')?->svg('x', ['class' => 'pg-icon', 'aria-hidden' => 'true']) !!}Anuluj</button>
    <button class="pg-button pg-button--danger" type="button">{!! \Simplysmart\Polygon\Icons\LocalIconSets::get('lucide')?->svg('trash-2', ['class' => 'pg-icon', 'aria-hidden' => 'true']) !!}Usuń</button>
</div>
```

<a name="disabled"></a>
## Nieaktywny

```blade preview
<div class="pg-demo">
    <button class="pg-button" type="button" disabled>{!! \Simplysmart\Polygon\Icons\LocalIconSets::get('lucide')?->svg('save', ['class' => 'pg-icon', 'aria-hidden' => 'true']) !!}Zapisz</button>
</div>
```

<a name="block"></a>
## Pełna szerokość

Tylko w formularzach logowania.

```blade preview
<button class="pg-button pg-button--block" type="button">Dalej{!! \Simplysmart\Polygon\Icons\LocalIconSets::get('lucide')?->svg('arrow-right', ['class' => 'pg-icon', 'aria-hidden' => 'true']) !!}</button>
```
