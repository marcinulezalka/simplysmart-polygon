# Klawiatura numeryczna PIN-u

- [Pole z klawiaturą](#field)

Mechanizm i podział odpowiedzialności: [Klawiatura numeryczna PIN-u](../docs/strefy.md#klawiatura-numeryczna-pin-u).

<a name="field"></a>
## Pole z klawiaturą

Przycisk pokazuje klawisze. „C” czyści pole, „OK” wysyła formularz, a jego zwykły przycisk jest wtedy ukryty. Klawisze cyfr
nie zmieniają koloru po najechaniu ani wciśnięciu.

```blade preview
<form onsubmit="return false">
    <div class="pg-field" data-pg-pinpad>
        <label class="pg-field__label" for="pin">Kod PIN</label>
        <input class="pg-input" id="pin" type="password" inputmode="numeric" maxlength="8">
        <button class="pg-button pg-button--secondary" type="button" aria-expanded="false" aria-controls="pin-pinpad" data-pg-pinpad-toggle hidden>Klawiatura</button>
        <x-polygon::pinpad id="pin-pinpad" />
    </div>
    <p><button class="pg-button" type="submit" data-pg-pinpad-submit>Zatwierdź</button></p>
</form>
```
