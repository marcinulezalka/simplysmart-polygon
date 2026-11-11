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

namespace Simplysmart\Polygon\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Simplysmart\Polygon\Enums\InvoicingProvider;
use Simplysmart\Polygon\Models\Relations\BelongsToOrganization;

/**
 * Konto systemu fakturowego organizacji (`invoicing_accounts`, docs/baza-danych.md §21): jedno na organizację główną
 * klienta — `provider` (Enums\InvoicingProvider), `subdomain` (konto w Fakturowni: `<subdomain>.fakturownia.pl`),
 * `api_token` szyfrowany (cast `encrypted`; nigdy nie trafia do widoków ani logów), opcjonalny `department_id` (dział
 * wystawcy w Fakturowni), `contractors_synced_at` (ostatnie udane pobranie kontrahentów) i `last_error` (ostatni błąd
 * połączenia). `name` — etykieta konta. Zapisywane przez Invoicing\SaveInvoicingAccount.
 *
 * @property int $organization_id
 * @property InvoicingProvider $provider
 * @property string $subdomain
 * @property string $api_token
 * @property string|null $department_id
 * @property CarbonImmutable|null $contractors_synced_at
 * @property string|null $last_error
 * @property-read Organization|null $organization
 */
final class InvoicingAccount extends DataModel
{
    use BelongsToOrganization;

    public const string TABLE = 'invoicing_accounts';

    /** Klucz obcy konta w tabeli powiązań kontrahentów. */
    public const string FOREIGN_KEY = 'invoicing_account_id';

    public const string PROVIDER = 'provider';

    public const string SUBDOMAIN = 'subdomain';

    public const string API_TOKEN = 'api_token';

    public const string DEPARTMENT_ID = 'department_id';

    public const string CONTRACTORS_SYNCED_AT = 'contractors_synced_at';

    public const string LAST_ERROR = 'last_error';

    protected $table = self::TABLE;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['is_active' => true, self::PROVIDER => InvoicingProvider::Fakturownia->value];

    /**
     * @var list<string>
     */
    protected $hidden = [self::API_TOKEN];

    /**
     * @return HasMany<InvoicingContractorLink, $this>
     */
    public function contractorLinks(): HasMany
    {
        return $this->hasMany(InvoicingContractorLink::class, self::FOREIGN_KEY);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            Organization::FOREIGN_KEY => 'integer',
            self::PROVIDER => InvoicingProvider::class,
            self::SUBDOMAIN => 'string',
            self::API_TOKEN => 'encrypted',
            self::DEPARTMENT_ID => 'string',
            self::CONTRACTORS_SYNCED_AT => 'immutable_datetime',
            self::LAST_ERROR => 'string',
        ];
    }
}
