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

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Simplysmart\Polygon\Contracts\CurrentOrganization;
use Simplysmart\Polygon\Models\ZoneAccount;
use Simplysmart\Polygon\Organizations\OrganizationContext;
use Simplysmart\Polygon\Zones\ZonePermission;

Route::get('desk', fn(): string => 'desk')->middleware(ZonePermission::middleware('desk.manage'))->name('desk');
Route::get('visits', fn(): string => 'visits')->middleware(ZonePermission::middleware('visits.admit'))->name('visits');
Route::get('context', fn(CurrentOrganization $organization): array => [
    'id' => $organization->id(),
    'role' => $organization->role(),
    'roles' => ($account = Auth::guard('beta')->user()) instanceof ZoneAccount ? $account->getRoleNames()->all() : [],
    'contexts' => array_map(static fn(OrganizationContext $context): array => [$context->organization->name, $context->role, $context->current, $context->default], $organization->contexts()),
])->name('context');
