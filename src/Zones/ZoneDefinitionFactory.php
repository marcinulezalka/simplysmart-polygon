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

use Illuminate\Config\Repository;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use Simplysmart\Polygon\Auth\ZoneAuthSettingsFactory;
use Simplysmart\Polygon\PolygonConfig;
use Simplysmart\Polygon\Models\ZoneAccount;

/**
 * Buduje i waliduje ZoneDefinition z configu paczki strefy (docs/strefy.md): `name` (wymagane), `slug`, `guard`,
 * `provider`, `model` (z nim strefa ma logowanie), `session`, `auth` (nadpisania `polygon.auth`), `texts`, `views`
 * (ekrany ZoneView) i `mail.views`.
 */
final readonly class ZoneDefinitionFactory
{
    private const string NAME_PATTERN = '/^[a-z][a-z0-9_]*$/';

    private const string VIEW_PATTERN = '/^[a-z][a-z0-9_-]*::[a-z0-9_.-]+$/';

    public function __construct(
        private Repository $config,
        private ZoneAuthSettingsFactory $authSettings,
    ) {}

    /**
     * @param array<array-key, mixed> $config
     *
     * @throws InvalidArgumentException Gdy config strefy jest błędny.
     */
    public function make(array $config, string $routeNamePrefix, string $routesPath): ZoneDefinition
    {
        $name = Arr::string($config, 'name');

        foreach (['name' => $name, 'route name prefix' => $routeNamePrefix] as $label => $value) {
            if (preg_match(self::NAME_PATTERN, $value) !== 1) {
                throw new InvalidArgumentException("Zone {$label} [{$value}] must match " . self::NAME_PATTERN . '.');
            }
        }

        $model = $this->model($config['model'] ?? null, $name);

        return new ZoneDefinition(
            name: $name,
            routeNamePrefix: $routeNamePrefix,
            slug: trim(Arr::string($config, 'slug', $name), '/'),
            guard: Arr::string($config, 'guard', $name),
            provider: Arr::string($config, 'provider', ZoneTable::Accounts->for($name)),
            model: $model,
            routesPath: rtrim($routesPath, '/'),
            session: $this->session($name, $model !== null, Arr::array($config, 'session', [])),
            authSettings: $model !== null ? $this->authSettings->make(Arr::array($config, 'auth', [])) : null,
            textNamespace: $this->nullableString($config, 'texts', null),
            views: $this->views($name, Arr::array($config, 'views', [])),
            mailViews: $this->mailViews($name, Arr::array($config, 'mail', [])),
        );
    }

    /**
     * @return class-string<ZoneAccount>|null
     */
    private function model(mixed $model, string $zone): ?string
    {
        if ($model === null) {
            return null;
        }

        if (! is_string($model) || ! is_subclass_of($model, ZoneAccount::class)) {
            throw new InvalidArgumentException("Zone [{$zone}] model must extend " . ZoneAccount::class . '.');
        }

        return $model;
    }

    /**
     * @param array<array-key, mixed> $session
     */
    private function session(string $zone, bool $requiresLogin, array $session): ZoneSessionSettings
    {
        return new ZoneSessionSettings(
            cookie: Arr::string($session, 'cookie', $zone . '_session'),
            table: $this->nullableString($session, 'table', $requiresLogin ? ZoneTable::Sessions->for($zone) : null),
            lifetime: $this->nullableInt($session, 'lifetime', $requiresLogin ? $this->config->integer(PolygonConfig::SESSION_LIFETIME) : null),
            expireOnClose: $this->nullableBool($session, 'expire_on_close', $requiresLogin ? true : null),
            sameSite: $this->nullableString($session, 'same_site', $requiresLogin ? 'strict' : 'lax'),
            encrypt: $this->nullableBool($session, 'encrypt', $requiresLogin ? true : null),
        );
    }

    /**
     * @param array<array-key, mixed> $views
     *
     * @return array<string, string>
     */
    private function views(string $zone, array $views): array
    {
        $result = [];

        foreach ($views as $screen => $view) {
            if (! is_string($screen) || ZoneView::tryFrom($screen) === null) {
                throw new InvalidArgumentException("Zone [{$zone}] views has an unknown screen [{$screen}]; allowed: " . implode(', ', array_column(ZoneView::cases(), 'value')) . '.');
            }

            if (! is_string($view) || preg_match(self::VIEW_PATTERN, $view) !== 1) {
                throw new InvalidArgumentException("Zone [{$zone}] view of [{$screen}] must be a namespaced view like `package::auth.{$screen}`.");
            }

            $result[$screen] = $view;
        }

        return $result;
    }

    /**
     * @param array<array-key, mixed> $mail
     */
    private function mailViews(string $zone, array $mail): ?string
    {
        $views = $mail['views'] ?? null;

        if ($views === null) {
            return null;
        }

        if (! is_string($views) || preg_match(self::VIEW_PATTERN, $views) !== 1) {
            throw new InvalidArgumentException("Zone [{$zone}] mail.views must be a namespaced view prefix like `package::mail`.");
        }

        return $views;
    }

    /**
     * @param array<array-key, mixed> $values
     */
    private function nullableString(array $values, string $key, ?string $default): ?string
    {
        return array_key_exists($key, $values) && $values[$key] !== null ? Arr::string($values, $key) : $default;
    }

    /**
     * @param array<array-key, mixed> $values
     */
    private function nullableInt(array $values, string $key, ?int $default): ?int
    {
        return array_key_exists($key, $values) && $values[$key] !== null ? Arr::integer($values, $key) : $default;
    }

    /**
     * @param array<array-key, mixed> $values
     */
    private function nullableBool(array $values, string $key, ?bool $default): ?bool
    {
        return array_key_exists($key, $values) && $values[$key] !== null ? Arr::boolean($values, $key) : $default;
    }
}
