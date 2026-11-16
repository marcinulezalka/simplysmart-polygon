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

namespace Simplysmart\Polygon\Icons;

use Illuminate\Support\HtmlString;
use InvalidArgumentException;
use Stringable;

/**
 * Nazwa ikony Iconify „zestaw:ikona” (np. `lucide:heart-pulse`, docs/ikony.md): prefiks zestawu i nazwa ikony — małe
 * litery, cyfry i myślniki. Ekran wstawia ją jako web component `<iconify-icon>` (html()); dane ikony przeglądarka bierze
 * z kolekcji dodanej w JS strefy (addCollection) albo z API Iconify. Czy zestaw jest dozwolony i czy ikona istnieje —
 * IconCatalog i Rules\IconName.
 */
final readonly class IconifyIcon implements Stringable
{
    /** Separator zestawu i nazwy. */
    public const string SEPARATOR = ':';

    /** Prefiks albo nazwa: małe litery i cyfry, części rozdzielone pojedynczym myślnikiem. */
    private const string PART = '[a-z0-9]+(?:-[a-z0-9]+)*';

    private function __construct(
        public string $prefix,
        public string $name,
    ) {}

    public static function tryFrom(string $value): ?self
    {
        if (preg_match('/^(' . self::PART . ')' . self::SEPARATOR . '(' . self::PART . ')$/', $value, $matches) !== 1) {
            return null;
        }

        return new self($matches[1], $matches[2]);
    }

    /**
     * @throws InvalidArgumentException Gdy wartość nie jest nazwą „zestaw:ikona”.
     */
    public static function from(string $value): self
    {
        return self::tryFrom($value) ?? throw new InvalidArgumentException("Invalid Iconify icon name [{$value}].");
    }

    public function value(): string
    {
        return $this->prefix . self::SEPARATOR . $this->name;
    }

    public function __toString(): string
    {
        return $this->value();
    }

    /**
     * Znacznik web componentu `<iconify-icon>` ukryty przed czytnikami ekranu (ikona obok tekstu albo z etykietą
     * w aria-label elementu nadrzędnego); $class — klasy wyglądu strefy.
     */
    public function html(string $class = ''): HtmlString
    {
        $classAttribute = trim($class) === '' ? '' : ' class="' . e(trim($class)) . '"';

        return new HtmlString('<iconify-icon icon="' . e($this->value()) . '"' . $classAttribute . ' aria-hidden="true"></iconify-icon>');
    }
}
