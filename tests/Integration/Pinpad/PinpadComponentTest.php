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

namespace Simplysmart\Polygon\Tests\Integration\Pinpad;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Klawiatura numeryczna PIN-u (`<x-polygon::pinpad>`): wszystkie cyfry, „C” i „OK” wysyłające formularz, ukryta do otwarcia
 * przez skrypt, z identyfikatorem i atrybutami strefy.
 */
class PinpadComponentTest extends TestCase
{
    public function test_it_renders_every_key_hidden_until_the_script_opens_it(): void
    {
        $this->app->setLocale('pl');
        $html = Blade::render('<x-polygon::pinpad id="pin-keys" class="zone-keys" />');

        foreach (range(0, 9) as $digit) {
            $this->assertSame(1, substr_count($html, 'data-pg-pinpad-digit="' . $digit . '"'));
        }

        $container = substr($html, 0, (int) strpos($html, '>') + 1);

        foreach (['class="pg-pinpad zone-keys"', 'id="pin-keys"', 'role="group"', 'aria-label="Klawiatura numeryczna"', ' hidden>'] as $attribute) {
            $this->assertStringContainsString($attribute, $container);
        }

        $this->assertStringContainsString('aria-label="C — wyczyść PIN"', $html);
        $this->assertMatchesRegularExpression('/<button class="pg-pinpad__key pg-pinpad__key--submit" type="submit">OK<\/button>/', $html);
        $this->assertSame(1, substr_count($html, 'type="submit"'));
    }
}
