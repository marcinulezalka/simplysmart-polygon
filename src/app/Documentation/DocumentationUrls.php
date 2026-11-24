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

namespace Simplysmart\Polygon\Documentation;

use Illuminate\Contracts\Routing\UrlGenerator;
use Simplysmart\Polygon\Contracts\CurrentZone;
use Simplysmart\Polygon\Contracts\ZoneRegistry;
use Simplysmart\Polygon\Http\DocumentationRoutes;
use Simplysmart\Polygon\Zones\ZoneDefinition;

/**
 * Adresy dokumentacji — także dla paczek stref (np. podpowiedź na ekranie linkująca do strony Wiki): w bieżącej strefie
 * z ekranami, a poza nią w jedynej takiej strefie aplikacji.
 */
final readonly class DocumentationUrls
{
    public function __construct(
        private UrlGenerator $urls,
        private CurrentZone $zone,
        private ZoneRegistry $zones,
    ) {}

    /**
     * Adres strony (`<strona>` albo `<klucz>/<strona>`, bez niej — początek systemu); null, gdy strefa nie jest
     * jednoznaczna.
     */
    public function page(DocumentationSystem $system, ?string $path = null, ?string $anchor = null): ?string
    {
        $zone = $this->zone();

        if ($zone === null) {
            return null;
        }

        $url = $this->urls->route($zone->routeName($this->route($zone, $system)), DocumentationRoutes::parameters($path));

        return $anchor === null ? $url : $url . '#' . $anchor;
    }

    /**
     * Poza strefą z `url_key` nie ma hasha sesji, więc adresem jest wejście bez niego.
     */
    private function route(ZoneDefinition $zone, DocumentationSystem $system): string
    {
        return $zone->urlKey && $this->current()?->name !== $zone->name ? DocumentationRoutes::entryName($system) : DocumentationRoutes::showName($system);
    }

    private function zone(): ?ZoneDefinition
    {
        $current = $this->current();

        if ($current?->hasScreens() === true) {
            return $current;
        }

        $zones = array_values(array_filter($this->zones->all(), static fn(ZoneDefinition $zone): bool => $zone->hasScreens()));

        return count($zones) === 1 ? $zones[0] : null;
    }

    private function current(): ?ZoneDefinition
    {
        return $this->zone->has() ? $this->zone->get() : null;
    }
}
