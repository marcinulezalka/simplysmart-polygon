# Formularze

- [Pole](#field)
- [Błąd](#error)
- [Wybór](#choice)

Etykieta nad polem, podpowiedź i błąd pod nim.

<a name="field"></a>
## Pole

```blade preview
<div class="pg-field">
    <label class="pg-field__label" for="name">Nazwa</label>
    <input class="pg-input" id="name" aria-describedby="name-hint">
    <p class="pg-field__hint" id="name-hint">Widoczna na fakturze.</p>
</div>
```

<a name="error"></a>
## Błąd

```blade preview
<div class="pg-field">
    <label class="pg-field__label" for="email">E-mail</label>
    <input class="pg-input" id="email" value="anna@" aria-invalid="true" aria-describedby="email-error">
    <p class="pg-field__error" id="email-error">Podaj poprawny adres e-mail.</p>
</div>
```

<a name="choice"></a>
## Wybór

```blade preview
<div class="pg-field">
    <label class="pg-field__label" for="country">Kraj</label>
    <select class="pg-input" id="country"><option>Polska</option><option>Niemcy</option></select>
</div>
<div class="pg-field">
    <label class="pg-check"><input type="checkbox" checked>Wyślij kopię</label>
</div>
```
