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

namespace Simplysmart\Polygon\Tests\Integration\Avatar;

use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Avatar użytkownika (`<x-polygon::avatar>`): zdjęcie albo inicjały, kolor użytkownika i nazwa dla czytnika ekranu.
 */
class AvatarComponentTest extends TestCase
{
    public function test_it_shows_the_initials_with_the_color_of_the_user(): void
    {
        $avatar = $this->avatar('<x-polygon::avatar initials="AN" color="#b45309" class="zone-avatar" />');

        $this->assertNotNull($avatar);
        $this->assertSame('AN', trim($avatar->textContent ?? ''));
        $this->assertStringContainsString('--pg-avatar-color: #b45309', (string) $avatar->getAttribute('style'));
        $this->assertStringContainsString('zone-avatar', $avatar->className);
        $this->assertNull($avatar->querySelector('img'));
    }

    public function test_a_photo_replaces_the_initials(): void
    {
        $avatar = $this->avatar('<x-polygon::avatar initials="AN" photo="/zdjecie" />');

        $this->assertSame('', trim($avatar->textContent ?? ''));
        $this->assertSame('/zdjecie', $avatar?->querySelector('img.pg-avatar__photo[alt=""]')?->getAttribute('src'));
    }

    public function test_it_is_decorative_unless_it_has_a_name(): void
    {
        $decorative = $this->avatar('<x-polygon::avatar initials="AN" />');
        $named = $this->avatar('<x-polygon::avatar initials="AN" label="Anna Nowak" />');

        $this->assertSame(['true', null, null], $this->accessibility($decorative));
        $this->assertSame([null, 'img', 'Anna Nowak'], $this->accessibility($named));
    }

    /**
     * @return array<string, array{string|null}>
     */
    public static function invalidColors(): array
    {
        return ['missing' => [null], 'name' => ['red'], 'extra declaration' => ['#b45309; background: url(//x)'], 'trailing newline' => ["#b45309\n"]];
    }

    #[DataProvider('invalidColors')]
    public function test_a_color_other_than_a_code_is_left_out(?string $color): void
    {
        $avatar = $this->avatar('<x-polygon::avatar initials="AN" :color="$color" />', ['color' => $color]);

        $this->assertNotNull($avatar);
        $this->assertFalse($avatar->hasAttribute('style'));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function avatar(string $blade, array $data = []): ?Element
    {
        return HTMLDocument::createFromString(Blade::render($blade, $data), LIBXML_NOERROR)->querySelector('span.pg-avatar');
    }

    /**
     * @return list<string|null>
     */
    private function accessibility(?Element $avatar): array
    {
        return array_map(static fn(string $name): ?string => $avatar?->getAttribute($name), ['aria-hidden', 'role', 'aria-label']);
    }
}
