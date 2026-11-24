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

namespace Simplysmart\Polygon\Tests\Integration\Zones;

use Illuminate\Support\Facades\Hash;
use Simplysmart\Polygon\Contracts\CurrentZone;
use Simplysmart\Polygon\Contracts\TrustedDeviceRepository;
use Simplysmart\Polygon\Contracts\ZoneRegistry;
use Simplysmart\Polygon\Http\StatusMessage;
use Simplysmart\Polygon\Tests\Fixtures\Zones\AlphaAccount;
use Simplysmart\Polygon\Tests\Fixtures\Zones\BetaZoneServiceProvider;
use Simplysmart\Polygon\Tests\Fixtures\Zones\OpenZoneServiceProvider;
use Simplysmart\Polygon\Tests\Fixtures\Zones\PinInAccountZoneServiceProvider;

/**
 * Definiowanie PIN-u po zalogowaniu (strefa bez kroku PinSetupStep): konto bez PIN-u wchodzi kodem tylko na stronę sekcji
 * PIN-u, zapis wpuszcza je dalej i unieważnia inne sesje, a urządzenie staje się zaufane dopiero z PIN-em.
 */
class PinInAccountTest extends SignInTestCase
{
    private const string PIN_PAGE = '/alpha/account/security#pin';

    protected function zoneProviders(): array
    {
        return [PinInAccountZoneServiceProvider::class, BetaZoneServiceProvider::class, OpenZoneServiceProvider::class];
    }

    public function test_account_without_a_pin_signs_in_with_the_code_only_to_define_it(): void
    {
        $account = $this->account(AlphaAccount::class);
        $this->requestCode();
        $this->submitCode($this->sentCode())->assertRedirect(url(self::PIN_PAGE));

        $this->assertAuthenticatedAs($account, 'alpha');
        $this->get('/alpha/page')->assertRedirect(url(self::PIN_PAGE));
        $this->getJson('/alpha/page')->assertForbidden();
        $this->post('/alpha/account/lock')->assertRedirect(url(self::PIN_PAGE));
        $this->delete('/alpha/account/devices')->assertRedirect(url(self::PIN_PAGE));
        $this->get('/alpha/account/security')->assertOk();

        $this->definePin('1111')->assertRedirect(url(self::PIN_PAGE));
        $this->assertNotSame('', $this->sessionError('pin'));
        $account->refresh();
        $this->assertNull($account->pinHash());

        $this->definePin(self::PIN)->assertRedirect('/alpha/dashboard')->assertSessionHas(StatusMessage::KEY, text('account.pin_defined'));
        $account->refresh();
        $this->assertTrue(Hash::check(self::PIN, (string) $account->pinHash()));
        $this->assertSame(1, $account->authEpoch());
        $this->get('/alpha/page')->assertOk();
    }

    public function test_the_signed_in_account_can_sign_out_without_defining_a_pin(): void
    {
        $this->signedIn($this->account(AlphaAccount::class), 'alpha');

        $this->post(route('alpha.auth.logout'))->assertRedirect();
        $this->assertGuest('alpha');
    }

    public function test_an_account_with_a_pin_cannot_define_it_again(): void
    {
        $account = $this->account(AlphaAccount::class, pin: self::PIN);
        $this->signedIn($account, 'alpha');

        $this->definePin('284619')->assertForbidden();
        $this->assertTrue(Hash::check(self::PIN, (string) $account->fresh()?->pinHash()));
    }

    public function test_defining_a_pin_ends_the_other_sessions_of_the_account(): void
    {
        $account = $this->account(AlphaAccount::class);
        $otherSession = $account->fresh();
        $this->signedIn($account, 'alpha');
        $this->definePin(self::PIN);

        $fresh = $account->fresh();
        $this->assertNotNull($otherSession);
        $this->assertNotNull($fresh);
        $this->signedIn($otherSession, 'alpha')->actingAs($fresh, 'alpha');
        $this->get('/alpha/page')->assertRedirect(route('alpha.auth.login'));
    }

    public function test_the_device_becomes_trusted_only_with_the_pin(): void
    {
        $account = $this->account(AlphaAccount::class);
        $this->requestCode();
        $this->post(route('alpha.auth.code.store'), ['code' => $this->sentCode(), 'remember_device' => '1'])->assertRedirect(url(self::PIN_PAGE));
        $this->assertSame(0, $this->trustedDevices($account));

        $this->definePin(self::PIN)->assertRedirect('/alpha/dashboard');
        $this->assertSame(1, $this->trustedDevices($account));
    }

    public function test_the_zone_has_no_pin_setup_step(): void
    {
        $this->assertTrue($this->app->make(ZoneRegistry::class)->get('alpha')->hasScreens());
        $this->get('/alpha/signin/pin-setup')->assertNotFound();
    }

    /**
     * @return \Illuminate\Testing\TestResponse<\Symfony\Component\HttpFoundation\Response>
     */
    private function definePin(string $pin): \Illuminate\Testing\TestResponse
    {
        return $this->post('/alpha/account/pin', ['pin' => $pin, 'pin_confirmation' => $pin]);
    }

    private function trustedDevices(AlphaAccount $account): int
    {
        $id = $account->getKey();
        $this->assertIsInt($id);

        return $this->app->make(CurrentZone::class)->within($this->app->make(ZoneRegistry::class)->get('alpha'), fn(): int => count($this->app->make(TrustedDeviceRepository::class)->activeFor($id)));
    }
}
