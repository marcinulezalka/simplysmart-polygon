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

namespace Simplysmart\Polygon\Auth\Concerns;

use Illuminate\Container\Container;
use Simplysmart\Polygon\Auth\AccountColumns;
use Simplysmart\Polygon\Auth\EmailAddress;
use Simplysmart\Polygon\Auth\EmailIndex;

/**
 * Kolumny konta strefy (Database\ZoneTables) w modelu, jak w admiro: e-mail szyfrowany, przy zapisie normalizowany
 * i indeksowany w `email_hash` (Auth\EmailIndex), IP ostatniego logowania i adres zapasowy szyfrowane, liczniki z wartością
 * domyślną w bazie także w nowym modelu (checksum, docs/baza-danych.md §5). Remember-me jest wyłączone.
 *
 * @method static void saving(\Closure|string $callback) Metoda modelu Eloquent.
 * @method void mergeCasts(array<string, string> $casts) Metoda modelu Eloquent.
 * @method $this mergeHidden(array<int, string> $hidden) Metoda modelu Eloquent.
 * @method mixed getAttribute(string $key) Metoda modelu Eloquent.
 * @method $this setAttribute(string $key, mixed $value) Metoda modelu Eloquent.
 * @method bool isDirty(array<int, string>|string|null $attributes = null) Metoda modelu Eloquent.
 */
trait HasZoneAccountColumns
{
    public static function bootHasZoneAccountColumns(): void
    {
        static::saving(static function (self $account): void {
            if (! $account->isDirty(AccountColumns::EMAIL)) {
                return;
            }

            $email = EmailAddress::normalize($account->emailAddress());
            $account->setAttribute(AccountColumns::EMAIL, $email);
            $account->setAttribute(AccountColumns::EMAIL_HASH, Container::getInstance()->make(EmailIndex::class)->of($email));
        });
    }

    public function initializeHasZoneAccountColumns(): void
    {
        $this->attributes += [AccountColumns::PIN_FAILED_ATTEMPTS => 0, AccountColumns::LOCK_LEVEL => 0, AccountColumns::AUTH_EPOCH => 0];
        $this->mergeCasts([
            AccountColumns::EMAIL => 'encrypted',
            AccountColumns::EMAIL_HASH => 'string',
            AccountColumns::PIN_HASH => 'string',
            AccountColumns::PIN_SET_AT => 'datetime',
            AccountColumns::PIN_FAILED_ATTEMPTS => 'integer',
            AccountColumns::LOCK_LEVEL => 'integer',
            AccountColumns::LOCKED_UNTIL => 'datetime',
            AccountColumns::AUTH_EPOCH => 'integer',
            AccountColumns::LAST_LOGIN_AT => 'datetime',
            AccountColumns::LAST_LOGIN_IP => 'encrypted',
            AccountColumns::RECOVERY_EMAIL => 'encrypted',
            AccountColumns::RECOVERY_EMAIL_SET_AT => 'datetime',
        ]);

        $this->mergeHidden([AccountColumns::PIN_HASH, AccountColumns::EMAIL_HASH]);
    }

    public function emailAddress(): string
    {
        $email = $this->getAttribute(AccountColumns::EMAIL);

        return is_string($email) ? $email : '';
    }

    public function getRememberTokenName(): string
    {
        return '';
    }
}
