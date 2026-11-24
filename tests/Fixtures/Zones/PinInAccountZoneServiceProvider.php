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

namespace Simplysmart\Polygon\Tests\Fixtures\Zones;

use Simplysmart\Polygon\Auth\SignIn\Steps\EmailCodeStep;
use Simplysmart\Polygon\Auth\SignIn\Steps\PinStep;

/**
 * Strefa testowa alpha, która definiuje PIN po zalogowaniu (kroki bez PinSetupStep).
 */
class PinInAccountZoneServiceProvider extends AlphaZoneServiceProvider
{
    protected function zoneConfig(): array
    {
        $config = parent::zoneConfig();
        $views = is_array($config['views'] ?? null) ? $config['views'] : [];
        unset($views['pin_setup']);

        return [...$config, 'views' => $views, 'auth' => ['steps' => [EmailCodeStep::class, PinStep::class]]];
    }
}
