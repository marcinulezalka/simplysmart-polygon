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

namespace Simplysmart\Polygon\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthManager;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Simplysmart\Polygon\Contracts\CurrentZone;
use Simplysmart\Polygon\Database\HistoryActor;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ustawia aktora historii zmian na czas żądania: w strefie (grupa `zone.<strefa>`) jej nazwa i konto z guarda strefy,
 * w strefie bez kont tylko jej nazwa, poza strefą (grupa `web`) guard `web` jako strefa i jego użytkownik, oraz identyfikator żądania; po odpowiedzi czyści
 * go (docs/baza-danych.md §4). Stoi po StartSession, więc konto z sesji jest już znane.
 */
final readonly class RecordHistoryActor
{
    public const string GUARD = 'web';

    public function __construct(
        private AuthManager $auth,
        private HistoryActor $actor,
        private CurrentZone $zone,
    ) {}

    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $zone = $this->zone->has() ? $this->zone->get() : null;
        $guard = $zone === null ? self::GUARD : ($zone->requiresLogin() ? $zone->guard : null);

        $this->actor->set($zone->name ?? self::GUARD, $guard === null ? null : $this->accountId($guard), (string) Str::uuid());

        try {
            return $next($request);
        } finally {
            $this->actor->clear();
        }
    }

    private function accountId(string $guard): int|string|null
    {
        $id = $this->auth->guard($guard)->id();

        return is_int($id) || is_string($id) ? $id : null;
    }
}
