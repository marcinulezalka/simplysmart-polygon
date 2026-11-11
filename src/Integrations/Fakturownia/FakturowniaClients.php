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

namespace Simplysmart\Polygon\Integrations\Fakturownia;

use Illuminate\Http\Client\Factory as Http;
use InvalidArgumentException;
use Simplysmart\Polygon\Models\InvoicingAccount;

/**
 * Fabryka klientów API Fakturowni: dla konta organizacji (for()) albo dla podanych danych dostępowych przed ich
 * zapisaniem (with() — sprawdzenie połączenia). Subdomena to tylko małe litery, cyfry i myślniki (SUBDOMAIN_PATTERN),
 * więc adres żądania nie wyjdzie poza `*.fakturownia.pl`.
 */
final readonly class FakturowniaClients
{
    /** Dozwolona subdomena konta. */
    public const string SUBDOMAIN_PATTERN = '/^[a-z0-9]([a-z0-9-]{0,98}[a-z0-9])?$/';

    public function __construct(
        private Http $http,
    ) {}

    public function for(InvoicingAccount $account): FakturowniaClient
    {
        return $this->with($account->subdomain, $account->api_token);
    }

    /**
     * @throws InvalidArgumentException Gdy subdomena ma niedozwolone znaki.
     */
    public function with(string $subdomain, string $token): FakturowniaClient
    {
        if (preg_match(self::SUBDOMAIN_PATTERN, $subdomain) !== 1) {
            throw new InvalidArgumentException('Niedozwolona subdomena konta Fakturowni.');
        }

        return new FakturowniaClient($this->http, $subdomain, $token);
    }

    /**
     * Subdomena z wpisanej wartości: bez protokołu, domeny `.fakturownia.pl` i ścieżki, małymi literami.
     */
    public static function subdomain(string $value): string
    {
        $value = strtolower(trim($value));
        $value = (string) preg_replace('#^https?://#', '', $value);
        $value = explode('/', $value, 2)[0];

        return (string) preg_replace('/\.fakturownia\.pl$/', '', $value);
    }
}
