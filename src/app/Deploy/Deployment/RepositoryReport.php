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

namespace Simplysmart\Polygon\Deploy\Deployment;

/**
 * Stan repozytorium projektu przed wdrożeniem.
 */
final readonly class RepositoryReport
{
    /**
     * @param  bool  $unpushed  Niewypchnięte commity — wdrożenie jest wstrzymywane.
     * @param  bool  $uncommitted  Niezacommitowane zmiany — ostrzeżenie (nie trafią na serwer).
     * @param  bool  $unfetched  Nie udało się pobrać stanu z `origin` — ostrzeżenie.
     */
    public function __construct(
        public bool $unpushed = false,
        public bool $uncommitted = false,
        public bool $unfetched = false,
    ) {}
}
