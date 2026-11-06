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

$autoload = $GLOBALS['_composer_autoload_path'] ?? null;

require is_string($autoload) ? $autoload : dirname(__DIR__) . '/vendor/autoload.php';
