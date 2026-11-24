# Pływający panel akcji

- [Panel](#panel)

Mechanizm i podział odpowiedzialności: [Pływający panel akcji](../docs/strefy.md#pływający-panel-akcji).

<a name="panel"></a>
## Panel

```blade preview
<div class="pg-demo__stage"></div>
<div class="pg-float pg-float--bottom-right" data-pg-float>
    <div class="pg-toasts" data-pg-toasts></div>
    <div class="pg-float__panel">
        <button type="button" data-pg-float-toggle aria-expanded="false">Akcje</button>
        <div data-pg-float-body hidden>
            <button type="button">Nowy wpis</button>
            <button type="button" data-pg-float-close>Zamknij</button>
        </div>
    </div>
</div>
```
