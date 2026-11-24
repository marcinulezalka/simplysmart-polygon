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

namespace Simplysmart\Polygon\Http;

use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Simplysmart\Polygon\Documentation\DocumentationSource;
use Simplysmart\Polygon\Documentation\DocumentationSystem;
use Simplysmart\Polygon\Http\Controllers\Documentation\DocumentationController;
use Simplysmart\Polygon\Http\Controllers\Documentation\DocumentationEntryController;
use Simplysmart\Polygon\Zones\AccessTier;
use Simplysmart\Polygon\Zones\ZoneDefinition;

/**
 * Wiki, Docs i Preview (Documentation\DocumentationSystem) w każdej strefie z ekranami, po zalogowaniu (poziom
 * Authenticated). Strefa z `url_key` ma strony pod hashem sesji i wejścia bez hasha (registerEntries()), do których
 * prowadzą linki spoza strefy: gość loguje się i wraca, zalogowany trafia na stronę z hashem swojej sesji.
 */
final readonly class DocumentationRoutes
{
    private const string SHOW = 'show';

    private const string ENTRY = 'entry';

    private const string SYSTEM = 'system';

    private const string PAGE = 'page';

    /** Adres strony: `<strona>` albo `<klucz>/<strona>`. */
    private const string PAGE_PATTERN = DocumentationSource::SLUG_PATTERN . '(?:/' . DocumentationSource::SLUG_PATTERN . ')?';

    public function __construct(
        private Router $router,
    ) {}

    public function register(ZoneDefinition $zone): void
    {
        foreach (DocumentationSystem::cases() as $system) {
            $this->add($zone, $system, DocumentationController::class, self::showName($system));
        }
    }

    public function registerEntries(ZoneDefinition $zone): void
    {
        foreach (DocumentationSystem::cases() as $system) {
            $this->add($zone, $system, DocumentationEntryController::class, self::entryName($system));
        }
    }

    /**
     * Krótka nazwa trasy strony systemu.
     */
    public static function showName(DocumentationSystem $system): string
    {
        return $system->value . '.' . self::SHOW;
    }

    /**
     * Krótka nazwa wejścia do systemu bez hasha sesji (tylko strefa z `url_key`).
     */
    public static function entryName(DocumentationSystem $system): string
    {
        return $system->value . '.' . self::ENTRY;
    }

    /**
     * Parametry trasy strony; bez strony — początek systemu.
     *
     * @return array<string, string>
     */
    public static function parameters(?string $page): array
    {
        return $page === null ? [] : [self::PAGE => $page];
    }

    /**
     * System trasy, na którą przyszło żądanie.
     */
    public static function system(Request $request): DocumentationSystem
    {
        return DocumentationSystem::from((string) self::parameter($request, self::SYSTEM));
    }

    /**
     * Adres strony z żądania; null — początek systemu.
     */
    public static function page(Request $request): ?string
    {
        return self::parameter($request, self::PAGE);
    }

    private static function parameter(Request $request, string $name): ?string
    {
        $value = $request->route($name);

        return is_string($value) ? $value : null;
    }

    /**
     * @param class-string $controller
     */
    private function add(ZoneDefinition $zone, DocumentationSystem $system, string $controller, string $name): void
    {
        $this->router->get($system->value . '/{' . self::PAGE . '?}', $controller)
            ->middleware(AccessTier::Authenticated->middleware($zone))
            ->where(self::PAGE, self::PAGE_PATTERN)
            ->defaults(self::SYSTEM, $system->value)
            ->name($name);
    }
}
