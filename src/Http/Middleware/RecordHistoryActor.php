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
use Simplysmart\Polygon\Database\HistoryActor;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ustawia aktora historii zmian na czas żądania grupy `web`: guard `web` jako strefa, zalogowany użytkownik
 * z jawnie nazwanego guarda i identyfikator żądania; po odpowiedzi czyści go (docs/baza-danych.md §4).
 * Stoi po StartSession (koniec grupy `web`), więc użytkownik z sesji jest już znany.
 */
final readonly class RecordHistoryActor
{
    public const string GUARD = 'web';

    public function __construct(
        private AuthManager $auth,
        private HistoryActor $actor,
    ) {}

    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->actor->set(self::GUARD, $this->accountId(), (string) Str::uuid());

        try {
            return $next($request);
        } finally {
            $this->actor->clear();
        }
    }

    private function accountId(): int|string|null
    {
        $id = $this->auth->guard(self::GUARD)->id();

        return is_int($id) || is_string($id) ? $id : null;
    }
}
