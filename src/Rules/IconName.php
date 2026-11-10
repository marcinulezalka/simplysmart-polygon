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

namespace Simplysmart\Polygon\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Simplysmart\Polygon\Icons\IconCatalog;
use Simplysmart\Polygon\Icons\IconifyIcon;

/**
 * Ikona wybrana przez użytkownika (docs/ikony.md): nazwa „zestaw:ikona” (Icons\IconifyIcon), zestaw z dozwolonych
 * (`polygon.icons.sets`) i ikona istniejąca w API Iconify (Icons\IconCatalog::exists(), z cache). Gdy API nie odpowiada,
 * reguła przepuszcza poprawną nazwę z dozwolonego zestawu — zapis nie czeka na zewnętrzną usługę.
 */
final readonly class IconName implements ValidationRule
{
    public function __construct(
        private IconCatalog $catalog,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $icon = is_string($value) ? IconifyIcon::tryFrom($value) : null;

        if ($icon === null) {
            $fail(text('icons.invalid'));

            return;
        }

        if (! $this->catalog->allows($icon)) {
            $fail(text('icons.set_not_allowed', ['sets' => implode(', ', $this->catalog->sets())]));

            return;
        }

        if ($this->catalog->exists($icon) === false) {
            $fail(text('icons.missing', ['icon' => $icon->value()]));
        }
    }
}
