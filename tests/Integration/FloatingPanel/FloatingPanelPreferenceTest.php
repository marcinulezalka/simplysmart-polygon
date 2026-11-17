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

namespace Simplysmart\Polygon\Tests\Integration\FloatingPanel;

use Simplysmart\Polygon\Database\ColumnNames;
use Simplysmart\Polygon\Enums\FloatingPanelPosition;
use Simplysmart\Polygon\FloatingPanel\FloatingPanelPreference;
use Simplysmart\Polygon\Http\StatusMessage;
use Simplysmart\Polygon\Tests\Fixtures\Zones\AlphaAccount;
use Simplysmart\Polygon\Tests\Integration\Zones\ZoneTestCase;

/**
 * Położenie pływającego panelu akcji konta (FloatingPanel\FloatingPanelPreference, trasa `account.floating-panel`):
 * domyślnie prawy dolny róg, zapis per konto obok innych ustawień domyślnych, nieznane położenie to błąd pola.
 */
class FloatingPanelPreferenceTest extends ZoneTestCase
{
    public function test_account_saves_its_floating_panel_position_next_to_other_default_settings(): void
    {
        $account = $this->account(AlphaAccount::class, [ColumnNames::JSON_DEFAULT_SETTINGS => ['report' => 'weekly']]);
        $preference = $this->app->make(FloatingPanelPreference::class);
        $this->assertSame(FloatingPanelPosition::BottomRight, $preference->position($account));

        $this->signedIn($account, 'alpha')->from('/alpha/organization')->put('/alpha/account/floating-panel', ['position' => 'top-left'])
            ->assertRedirect('/alpha/organization')
            ->assertSessionHas(StatusMessage::KEY, text('account.floating_panel.saved', ['position' => FloatingPanelPosition::TopLeft->label()]));

        $this->assertSame(FloatingPanelPosition::TopLeft, $preference->position($account->refresh()));
        $this->assertSame(['report' => 'weekly', FloatingPanelPreference::SETTING => 'top-left'], $account->getAttribute(ColumnNames::JSON_DEFAULT_SETTINGS));
    }

    public function test_unknown_position_is_refused_and_the_saved_one_stays(): void
    {
        $account = $this->account(AlphaAccount::class, [ColumnNames::JSON_DEFAULT_SETTINGS => [FloatingPanelPreference::SETTING => 'top-center']]);

        $this->signedIn($account, 'alpha')->put('/alpha/account/floating-panel', ['position' => 'center'])->assertRedirect();

        $this->assertNotSame('', $this->sessionError('position'));
        $this->assertSame(FloatingPanelPosition::TopCenter, $this->app->make(FloatingPanelPreference::class)->position($account->refresh()));
    }
}
