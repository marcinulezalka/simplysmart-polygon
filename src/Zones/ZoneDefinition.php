<?php

declare(strict_types=1);

/**
 * This file is part of simplysmart/polygon.
 *
 * Copyright (c) 2026 Marcin Ulezalka <marcin.ulezalka@simplysmart.pl>
 *
 * Licensed under the Simplysmart Restricted Source License 1.0.
 * See the LICENSE file distributed with this source code.
 */

namespace Simplysmart\Polygon\Zones;

use LogicException;
use Simplysmart\Polygon\Auth\ZoneAuthSettings;
use Simplysmart\Polygon\Models\ZoneAccount;

/**
 * Niezmienna definicja jednej strefy (budowana przez ZoneDefinitionFactory z configu paczki strefy, docs/strefy.md).
 */
final readonly class ZoneDefinition
{
    /**
     * @param string $name Nazwa strefy (klucze cache, nazwa ciasteczka, grupa middleware).
     * @param string $routeNamePrefix Stały prefiks nazw tras (bez kropki), niezależny od configu.
     * @param string $slug Segment URL strefy; '' = bez segmentu.
     * @param string $guard Guard strefy.
     * @param string $provider Provider użytkowników guardu.
     * @param class-string<ZoneAccount>|null $model Model kont strefy (Models\ZoneAccount); null = strefa bez logowania.
     * @param string $routesPath Katalog plików tras strefy (public.php, account.php, zone.php).
     * @param ZoneAuthSettings|null $authSettings Ustawienia logowania; null = strefa bez logowania.
     * @param string|null $textNamespace Namespace tekstów paczki strefy (`texts`, np. `<namespace>::sign_in.<klucz>`);
     *                                   null = teksty polygon.
     * @param array<string, string> $views Widoki ekranów logowania i konta (`views`: ZoneView → nazwa widoku).
     * @param string|null $mailViews Prefiks widoków e-maili strefy (`mail.views`, np. `shop::mail`): `<prefiks>.layout`,
     *                               `<prefiks>.<szablon>` (HTML) i opcjonalnie `<prefiks>.text.<szablon>`.
     * @param bool $urlKey Hash sesji w adresie ekranów po zalogowaniu (`url_key`, `/<slug>/<hash>/…`, Zones\ZoneUrlKey).
     */
    public function __construct(
        public string $name,
        public string $routeNamePrefix,
        public string $slug,
        public string $guard,
        public string $provider,
        public ?string $model,
        public string $routesPath,
        public ZoneSessionSettings $session,
        public ?ZoneAuthSettings $authSettings = null,
        public ?string $textNamespace = null,
        public array $views = [],
        public ?string $mailViews = null,
        public bool $urlKey = false,
    ) {}

    /**
     * Klucz tekstu w namespace paczki strefy (dla `text()`: bez tekstu w paczce kaskada sięga do polygon).
     */
    public function textKey(string $key): string
    {
        return $this->textNamespace === null ? $key : $this->textNamespace . '::' . $key;
    }

    /**
     * Tekst ekranu logowania strefy: najpierw osobny dla strefy (`<namespace>::sign_in.<strefa>.<klucz>`), potem
     * paczki (`<namespace>::sign_in.<klucz>`), na końcu polygon (`sign_in.<klucz>`).
     *
     * @param array<string, scalar> $replace
     */
    public function signInText(string $key, array $replace = []): string
    {
        if ($this->textNamespace !== null) {
            foreach ([$this->textKey('sign_in.' . $this->name . '.' . $key), $this->textKey('sign_in.' . $key)] as $candidate) {
                $line = text($candidate, $replace);

                if ($line !== $candidate) {
                    return $line;
                }
            }
        }

        return text('sign_in.' . $key, $replace);
    }

    /**
     * Etykieta roli strefy (`roles.<strefa>.<rola>` w tekstach paczki strefy, potem katalog ról polygon `roles.<rola>`).
     */
    public function roleLabel(string $role): string
    {
        $key = $this->textKey('roles.' . $this->name . '.' . $role);
        $label = text($key);

        return $label !== $key ? $label : text('polygon::roles.' . $role);
    }

    /**
     * Czy strefa ma konta i logowanie.
     */
    public function requiresLogin(): bool
    {
        return $this->model !== null;
    }

    /**
     * Czy strefa ma ekrany logowania i konta (komplet widoków ZoneView); bez nich trasy logowania się nie rejestrują.
     */
    public function hasScreens(): bool
    {
        return $this->requiresLogin() && array_diff(array_column(ZoneView::cases(), 'value'), array_keys($this->views)) === [];
    }

    /**
     * Nazwa widoku ekranu strefy.
     *
     * @throws LogicException Gdy strefa nie dostarcza tego ekranu.
     */
    public function view(ZoneView $screen): string
    {
        return $this->views[$screen->value] ?? throw new LogicException("Zone [{$this->name}] has no view for the screen [{$screen->value}].");
    }

    /**
     * Ustawienia logowania strefy.
     *
     * @throws LogicException Gdy strefa nie ma logowania.
     */
    public function auth(): ZoneAuthSettings
    {
        return $this->authSettings ?? throw new LogicException("Zone [{$this->name}] has no sign-in.");
    }

    /**
     * Model kont strefy logującej się kodem i PIN-em.
     *
     * @return class-string<ZoneAccount>
     *
     * @throws LogicException Gdy strefa nie ma kont.
     */
    public function accountModel(): string
    {
        return $this->model ?? throw new LogicException("Zone [{$this->name}] has no account model.");
    }

    /**
     * Pełna nazwa trasy strefy, np. "alpha.dashboard".
     */
    public function routeName(string $name): string
    {
        return $this->routeNamePrefix . '.' . $name;
    }
}
