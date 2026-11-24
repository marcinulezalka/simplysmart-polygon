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

namespace Simplysmart\Polygon\Tests\Documentation;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Simplysmart\Polygon\Documentation\DocumentationSource;
use Simplysmart\Polygon\Documentation\DocumentationSources;
use Simplysmart\Polygon\Documentation\DocumentationSystem;

/**
 * Rejestr źródeł dokumentacji (Documentation\DocumentationSources): kolejność, widoczność w strefie, błędne źródła.
 */
final class DocumentationSourcesTest extends TestCase
{
    #[Test]
    public function polygon_comes_first_and_a_zone_source_is_visible_only_in_its_zone(): void
    {
        $sources = new DocumentationSources();
        $sources->add(new DocumentationSource(DocumentationSystem::Docs, 'shop', '/tmp', zone: 'shop'));

        $this->assertSame(['polygon', 'shop'], $this->keys($sources, 'shop'));
        $this->assertSame(['polygon'], $this->keys($sources, 'other'));
        $this->assertSame(['polygon'], $this->keys($sources, null));
    }

    #[Test]
    public function docs_of_a_package_must_name_their_zone(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DocumentationSource(DocumentationSystem::Docs, 'shop', '/tmp');
    }

    #[Test]
    public function a_source_key_is_unique_in_its_system(): void
    {
        $sources = new DocumentationSources();
        $sources->add(new DocumentationSource(DocumentationSystem::Wiki, 'shop', '/tmp'));

        $this->expectException(InvalidArgumentException::class);

        $sources->add(new DocumentationSource(DocumentationSystem::Wiki, 'shop', '/tmp'));
    }

    /**
     * @return list<string>
     */
    private function keys(DocumentationSources $sources, ?string $zone): array
    {
        return array_map(static fn(DocumentationSource $source): string => $source->key, $sources->visible(DocumentationSystem::Docs, $zone));
    }
}
