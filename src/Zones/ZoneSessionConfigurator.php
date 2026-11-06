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

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;

/**
 * Przełącza config sesji na strefę (przed StartSession) i przywraca go po odpowiedzi.
 *
 * Bazowy config/session.php zapamiętuje raz, przy tworzeniu, więc kolejna strefa w tym samym
 * procesie (testy, Octane) zawsze startuje od wartości aplikacji, a nie od poprzedniej strefy.
 *
 * Magazyn strefy staje się też singletonem `session.store`, z którego guard strefy czyta zalogowane konto — nawet gdy
 * singleton rozwiązano przed middleware: kontroler trasy `Route::view` (ViewController → ResponseFactory → Redirector)
 * powstaje przed UseZone i bez tego guard widziałby domyślną sesję aplikacji, czyli niezalogowane konto.
 */
final readonly class ZoneSessionConfigurator
{
    private const string STORE = 'session.store';

    /**
     * @param array<array-key, mixed> $baseSessionConfig
     * @param bool $forceSecure Ciasteczko tylko przez HTTPS (każde środowisko poza local).
     */
    public function __construct(
        private Container $container,
        private Repository $config,
        private ZoneSessionManager $session,
        private array $baseSessionConfig,
        private bool $forceSecure,
    ) {}

    /**
     * Ustawia sesję strefy i jej własny magazyn (tworzony z tego configu przy pierwszym użyciu).
     */
    public function apply(ZoneDefinition $zone): void
    {
        $settings = array_replace($this->baseSessionConfig, $zone->session->toSessionConfig());

        if ($this->forceSecure) {
            $settings['secure'] = true;
        }

        $this->config->set('session', $settings);
        $this->session->useZone($zone->name);
        $this->useStore();
    }

    /**
     * Przywraca config sesji aplikacji; `session.store` zostaje magazynem strefy tego żądania (po odpowiedzi czytają
     * go np. asercje sesji testów), a następne apply() i tak podstawia magazyn swojej strefy.
     */
    public function restore(): void
    {
        $this->config->set('session', $this->baseSessionConfig);
        $this->session->useZone(null);
    }

    /**
     * Podstawia magazyn bieżącej strefy pod singleton `session.store` (z niego tworzy się guard strefy).
     */
    private function useStore(): void
    {
        $this->container->instance(self::STORE, $this->session->driver());
    }
}
