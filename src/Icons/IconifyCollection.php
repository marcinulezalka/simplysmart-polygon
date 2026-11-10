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

use InvalidArgumentException;

/**
 * Lokalny zestaw ikon w formacie Iconify JSON (`{prefix, icons: {nazwa: {body, width?, height?, left?, top?}}, aliases?,
 * width?, height?}`, docs/ikony.md) — wycinek zestawu z API (komenda polygon:icons:subset) albo własny zestaw strefy.
 * Ten sam plik ładuje JS strefy (addCollection w iconify-icon), a serwer składa z niego SVG tam, gdzie web component
 * nie działa — wydruki PDF (svg()); e-maile dostają ikony jako PNG (polygon:icons:png).
 */
final readonly class IconifyCollection
{
    /** Domyślny rozmiar siatki ikon Iconify. */
    private const int DEFAULT_SIZE = 16;

    /**
     * @param array<string, array{body: string, width?: int|float, height?: int|float, left?: int|float, top?: int|float}> $icons
     * @param array<string, string> $aliases Alias => nazwa ikony.
     */
    private function __construct(
        public string $prefix,
        private array $icons,
        private array $aliases,
        private int|float $width,
        private int|float $height,
    ) {}

    /**
     * @throws InvalidArgumentException Gdy pliku nie ma albo nie jest zestawem Iconify JSON.
     */
    public static function load(string $path): self
    {
        $json = is_file($path) ? file_get_contents($path) : false;
        $data = is_string($json) ? json_decode($json, true) : null;

        if (! is_array($data)) {
            throw new InvalidArgumentException("Iconify JSON file [{$path}] is missing or invalid.");
        }

        return self::fromArray($data);
    }

    /**
     * @param array<mixed> $data
     *
     * @throws InvalidArgumentException Gdy dane nie są zestawem Iconify JSON.
     */
    public static function fromArray(array $data): self
    {
        $prefix = $data['prefix'] ?? null;
        $rawIcons = $data['icons'] ?? null;

        if (! is_string($prefix) || ! is_array($rawIcons)) {
            throw new InvalidArgumentException('Iconify JSON needs a prefix and icons.');
        }

        $icons = [];

        foreach ($rawIcons as $name => $icon) {
            if (is_string($name) && is_array($icon) && is_string($icon['body'] ?? null)) {
                $icons[$name] = ['body' => $icon['body'], ...array_filter(
                    ['width' => $icon['width'] ?? null, 'height' => $icon['height'] ?? null, 'left' => $icon['left'] ?? null, 'top' => $icon['top'] ?? null],
                    static fn(mixed $value): bool => is_int($value) || is_float($value),
                )];
            }
        }

        $aliases = [];

        foreach (is_array($data['aliases'] ?? null) ? $data['aliases'] : [] as $alias => $target) {
            if (is_string($alias) && is_array($target) && is_string($target['parent'] ?? null)) {
                $aliases[$alias] = $target['parent'];
            }
        }

        $width = $data['width'] ?? self::DEFAULT_SIZE;
        $height = $data['height'] ?? self::DEFAULT_SIZE;

        /** @var array<string, array{body: string, width?: int|float, height?: int|float, left?: int|float, top?: int|float}> $icons */
        return new self($prefix, $icons, $aliases, is_int($width) || is_float($width) ? $width : self::DEFAULT_SIZE, is_int($height) || is_float($height) ? $height : self::DEFAULT_SIZE);
    }

    public function has(string $name): bool
    {
        return isset($this->icons[$this->aliases[$name] ?? $name]);
    }

    /**
     * @return list<string> Nazwy ikon (bez aliasów).
     */
    public function names(): array
    {
        return array_keys($this->icons);
    }

    /**
     * SVG ikony do osadzenia po stronie serwera; $attributes — dodatkowe atrybuty znacznika `<svg>` (np. class, width).
     * Null, gdy ikony nie ma w zestawie.
     *
     * @param array<string, string> $attributes
     */
    public function svg(string $name, array $attributes = []): ?string
    {
        $icon = $this->icons[$this->aliases[$name] ?? $name] ?? null;

        if ($icon === null) {
            return null;
        }

        $viewBox = implode(' ', [$icon['left'] ?? 0, $icon['top'] ?? 0, $icon['width'] ?? $this->width, $icon['height'] ?? $this->height]);
        $extra = '';

        foreach ($attributes as $attribute => $value) {
            $extra .= ' ' . $attribute . '="' . e($value) . '"';
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="' . $viewBox . '"' . $extra . '>' . $icon['body'] . '</svg>';
    }
}
