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

namespace Simplysmart\Polygon\Providers;

use Illuminate\Support\ServiceProvider;
use Simplysmart\Polygon\Mail\NoreplyMailer;
use Simplysmart\Polygon\Mail\SmtpNoreplyMailer;

/**
 * Nadawca „noreply” organizacji (docs/baza-danych.md §22): wiązanie interfejsu wysyłki z prawdziwym serwerem SMTP.
 */
final class MailServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(NoreplyMailer::class, SmtpNoreplyMailer::class);
    }
}
