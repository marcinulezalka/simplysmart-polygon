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

use Illuminate\Routing\Router;
use InvalidArgumentException;
use LogicException;
use Simplysmart\Polygon\Http\AccountRoutes;
use Simplysmart\Polygon\Http\Middleware\RequireZoneUrlKey;
use Simplysmart\Polygon\Http\SignInRoutes;
use Simplysmart\Polygon\Localization\DictionaryLocales;

/**
 * Rejestruje trasy strefy: prefiks URL (slug), prefiks nazw `<strefa>.`, grupę middleware strefy oraz middleware
 * poziomu dostępu każdego pliku tras (public.php, account.php, zone.php; brakujący plik jest pomijany, pliki definiują
 * ścieżki względne i krótkie nazwy). Strefa z logowaniem i kompletem ekranów (ZoneDefinition::hasScreens()) dostaje
 * też trasy logowania (Http\SignInRoutes) i konta (Http\AccountRoutes). Strefa z `url_key` ma trasy po zalogowaniu (konto,
 * account.php, zone.php) pod hashem sesji: `/<slug>/<hash>/…` (Zones\ZoneUrlKey, Http\Middleware\RequireZoneUrlKey),
 * a logowanie i public.php zostają pod `/<slug>/…`.
 */
final readonly class ZoneRouteRegistrar
{
    /**
     * @var array<string, AccessTier>
     */
    private const array ROUTE_FILES = [
        'public.php' => AccessTier::Public,
        'account.php' => AccessTier::Authenticated,
        'zone.php' => AccessTier::Authenticated,
    ];

    public function __construct(
        private Router $router,
        private DictionaryLocales $locales,
        private SignInRoutes $signIn,
        private AccountRoutes $account,
    ) {}

    /**
     * @throws InvalidArgumentException Gdy slug strefy jest kodem włączonego języka.
     * @throws LogicException Gdy strefa bez logowania ma trasy dla zalogowanych.
     */
    public function register(ZoneDefinition $zone): void
    {
        if (in_array($zone->slug, $this->locales->interfaceLocales(), true)) {
            throw new InvalidArgumentException("Zone [{$zone->name}] slug [{$zone->slug}] must not be a locale code.");
        }

        $attributes = [
            'middleware' => [ZoneMiddlewareRegistrar::group($zone)],
            'prefix' => $zone->slug,
            'as' => $zone->routeNamePrefix . '.',
        ];

        if (! $zone->urlKey) {
            $this->router->group($attributes, function () use ($zone): void {
                if ($zone->hasScreens()) {
                    $this->signIn->register($zone);
                    $this->account->register($zone);
                }

                foreach (self::ROUTE_FILES as $file => $tier) {
                    $this->registerFile($zone, $zone->routesPath . '/' . $file, $tier);
                }
            });

            return;
        }

        $this->router->group($attributes, function () use ($zone): void {
            if ($zone->hasScreens()) {
                $this->signIn->register($zone);
            }

            $this->registerFiles($zone, false);
        });

        $hashed = [...$attributes, 'prefix' => $zone->slug . '/{' . ZoneUrlKey::PARAMETER . '}', 'middleware' => [...$attributes['middleware'], RequireZoneUrlKey::class], 'where' => [ZoneUrlKey::PARAMETER => ZoneUrlKey::PATTERN]];

        $this->router->group($hashed, function () use ($zone): void {
            if ($zone->hasScreens()) {
                $this->account->register($zone);
            }

            $this->registerFiles($zone, true);
        });
    }

    /**
     * Pliki tras jednego poziomu: z logowaniem ($authenticated) albo publiczne.
     */
    private function registerFiles(ZoneDefinition $zone, bool $authenticated): void
    {
        foreach (self::ROUTE_FILES as $file => $tier) {
            if ($tier->requiresLogin() === $authenticated) {
                $this->registerFile($zone, $zone->routesPath . '/' . $file, $tier);
            }
        }
    }

    private function registerFile(ZoneDefinition $zone, string $path, AccessTier $tier): void
    {
        if (! is_file($path)) {
            return;
        }

        if ($tier->requiresLogin() && ! $zone->requiresLogin()) {
            throw new LogicException("Zone [{$zone->name}] has no sign-in, so it cannot have [{$path}].");
        }

        $this->router->group(['middleware' => $tier->middleware($zone)], $path);
    }
}
