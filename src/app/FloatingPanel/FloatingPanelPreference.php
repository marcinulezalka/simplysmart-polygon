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

namespace Simplysmart\Polygon\FloatingPanel;

use Simplysmart\Polygon\Database\ColumnNames;
use Simplysmart\Polygon\Enums\FloatingPanelPosition;
use Simplysmart\Polygon\Models\ZoneAccount;

/**
 * Położenie pływającego panelu akcji wybrane przez konto (Ustawienia → Wygląd strefy, trasa
 * `account.floating-panel`), zapisane per osoba w `json_default_settings.floating_panel`; bez zapisu albo przy
 * nieznanej wartości — FloatingPanelPosition::DEFAULT (prawy dolny róg).
 */
final readonly class FloatingPanelPreference
{
    /** Klucz ustawienia w `json_default_settings` konta. */
    public const string SETTING = 'floating_panel';

    public function position(?ZoneAccount $account): FloatingPanelPosition
    {
        $value = $account?->defaultSetting(self::SETTING);

        return is_string($value) ? FloatingPanelPosition::tryFrom($value) ?? FloatingPanelPosition::DEFAULT : FloatingPanelPosition::DEFAULT;
    }

    /**
     * Zapisuje położenie; pozostałe ustawienia `json_default_settings` zostają.
     */
    public function save(ZoneAccount $account, FloatingPanelPosition $position): void
    {
        $current = $account->getAttribute(ColumnNames::JSON_DEFAULT_SETTINGS);
        $account->forceFill([ColumnNames::JSON_DEFAULT_SETTINGS => [...(is_array($current) ? $current : []), self::SETTING => $position->value]])->save();
    }
}
