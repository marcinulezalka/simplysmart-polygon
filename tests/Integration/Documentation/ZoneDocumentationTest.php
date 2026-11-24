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

namespace Simplysmart\Polygon\Tests\Integration\Documentation;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Simplysmart\Polygon\Database\ZoneTables;
use Simplysmart\Polygon\Documentation\DocumentationSource;
use Simplysmart\Polygon\Documentation\DocumentationSources;
use Simplysmart\Polygon\Documentation\DocumentationSystem;
use Simplysmart\Polygon\Documentation\DocumentationUrls;
use Simplysmart\Polygon\Tests\Fixtures\Zones\AlphaAccount;
use Simplysmart\Polygon\Tests\Fixtures\Zones\BetaAccount;
use Simplysmart\Polygon\Tests\Fixtures\Zones\GammaAccount;
use Simplysmart\Polygon\Tests\Fixtures\Zones\GammaZoneServiceProvider;
use Simplysmart\Polygon\Tests\Integration\Zones\ZoneTestCase;

/**
 * Wiki, Docs i Preview w strefie (Http\DocumentationRoutes): tylko po zalogowaniu, źródło paczki tylko w jej strefie,
 * wejście bez hasha sesji.
 */
class ZoneDocumentationTest extends ZoneTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(ZoneTables::class)->create('gamma');
        $this->registerZone(GammaZoneServiceProvider::class);
        $this->app->make(DocumentationSources::class)->add(new DocumentationSource(DocumentationSystem::Docs, 'shop', dirname(__DIR__, 2) . '/Fixtures/Documentation/docs', zone: 'alpha'));
    }

    /**
     * @return array<string, array{DocumentationSystem, string}>
     */
    public static function pages(): array
    {
        return [
            'wiki' => [DocumentationSystem::Wiki, 'sign-in'],
            'docs' => [DocumentationSystem::Docs, 'deploy'],
            'preview' => [DocumentationSystem::Preview, 'toasts'],
        ];
    }

    #[DataProvider('pages')]
    public function test_a_guest_signs_in_first(DocumentationSystem $system, string $page): void
    {
        $this->get("/alpha/{$system->value}/{$page}")->assertRedirect(route('alpha.auth.login'));
        $this->get("/gamma/{$system->value}/{$page}")->assertRedirect(route('gamma.auth.login'));
    }

    #[DataProvider('pages')]
    public function test_an_account_of_another_zone_is_a_guest(DocumentationSystem $system, string $page): void
    {
        $this->signedIn($this->account(BetaAccount::class), 'beta')->get("/alpha/{$system->value}/{$page}")->assertRedirect(route('alpha.auth.login'));
    }

    #[DataProvider('pages')]
    public function test_there_is_no_public_address(DocumentationSystem $system, string $page): void
    {
        $this->get("/polygon/{$system->value}/{$page}")->assertNotFound();
        $this->get("/open/{$system->value}/{$page}")->assertNotFound();
        $this->assertFalse(Route::has("polygon.{$system->value}.show"));
        $this->assertFalse(Route::has("open.{$system->value}.show"));
    }

    public function test_a_package_source_is_visible_only_in_its_zone(): void
    {
        $this->signedIn($this->account(AlphaAccount::class), 'alpha')->get('/alpha/docs/shop/orders')
            ->assertOk()
            ->assertSee('Zamówienia sklepu')
            ->assertSee('href="' . e(route('alpha.docs.show', 'shop/orders')) . '#x"', false);
        $this->signedIn($this->account(BetaAccount::class), 'beta')->get('/beta/docs/shop/orders')->assertNotFound();
        $this->get('/beta/docs')->assertOk()->assertDontSee('Zamówienia');
    }

    #[DataProvider('pages')]
    public function test_the_entry_leads_to_the_page_under_the_session_hash(DocumentationSystem $system, string $page): void
    {
        $this->signedIn($this->account(GammaAccount::class), 'gamma');
        $target = $this->get("/gamma/{$system->value}/{$page}")->assertRedirect()->headers->get('Location');

        $this->assertIsString($target);
        $this->assertMatchesRegularExpression("#/gamma/[a-z0-9]{20}/{$system->value}/{$page}$#", $target);
        $this->get($target)->assertOk()->assertSee('<html', false);
    }

    public function test_an_address_is_unknown_outside_a_zone_when_several_zones_have_documentation(): void
    {
        $this->assertNull($this->app->make(DocumentationUrls::class)->page(DocumentationSystem::Wiki, 'sign-in'));
    }
}
