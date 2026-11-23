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

namespace Simplysmart\Polygon\Enums;

use Simplysmart\Polygon\Localization\PackageText;

/**
 * Szyfrowanie połączenia ze skrzynką pocztową (nadawca NoReply organizacji — Models\NoreplyMailboxAccount, skrzynki kont
 * w strefach): SSL/TLS od początku połączenia albo STARTTLS. Brak szyfrowania to pusta wartość pola (null w bazie),
 * nie przypadek enuma — stąd options() z pustą wartością dla list wyboru i smtpScheme() dla nullable pola.
 * Teksty: `enums.mail_encryption.<value>` i `enums.mail_encryption.none`.
 */
enum MailEncryption: string
{
    case Ssl = 'ssl';
    case Tls = 'tls';

    public function label(): string
    {
        return PackageText::get('enums.mail_encryption.' . $this->value);
    }

    /**
     * Schemat transportu SMTP Laravela dla zapisanej wartości pola: `smtps` dla SSL, `smtp` (STARTTLS albo bez
     * szyfrowania) dla reszty, także pustej.
     */
    public static function smtpScheme(?string $encryption): string
    {
        return self::tryFrom($encryption ?? '') === self::Ssl ? 'smtps' : 'smtp';
    }

    /**
     * Opcje listy wyboru: wartość pola => etykieta, z pustą wartością „bez szyfrowania” na końcu.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return [...$options, '' => PackageText::get('enums.mail_encryption.none')];
    }
}
