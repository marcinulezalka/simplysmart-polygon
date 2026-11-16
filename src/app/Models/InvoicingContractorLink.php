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
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Powiązanie kontrahenta z klientem w systemie fakturowym (`invoicing_contractor_links`, docs/baza-danych.md §21):
 * `external_id` — id klienta w Fakturowni (unikalne w koncie; kontrahent ma w koncie jedno powiązanie),
 * `external_updated_at` — chwila ostatniej zmiany po stronie Fakturowni widziana przy synchronizacji, `synced_at` —
 * chwila ostatniej synchronizacji, `synced_checksum` — odcisk zsynchronizowanych danych (Invoicing\ContractorFingerprint):
 * kontrahent z tym samym odciskiem nie jest wysyłany ponownie, więc zapis z pobrania nie wraca do Fakturowni.
 * `name` — nazwa klienta w chwili synchronizacji.
 *
 * @property int $invoicing_account_id
 * @property int $contractor_id
 * @property string $external_id
 * @property CarbonImmutable|null $external_updated_at
 * @property CarbonImmutable|null $synced_at
 * @property string|null $synced_checksum
 * @property-read InvoicingAccount|null $account
 * @property-read Contractor|null $contractor
 */
final class InvoicingContractorLink extends DataModel
{
    public const string TABLE = 'invoicing_contractor_links';

    public const string EXTERNAL_ID = 'external_id';

    public const string EXTERNAL_UPDATED_AT = 'external_updated_at';

    public const string SYNCED_AT = 'synced_at';

    public const string SYNCED_CHECKSUM = 'synced_checksum';

    protected $table = self::TABLE;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['is_active' => true];

    /**
     * @return BelongsTo<InvoicingAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(InvoicingAccount::class, InvoicingAccount::FOREIGN_KEY);
    }

    /**
     * @return BelongsTo<Contractor, $this>
     */
    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class, Contractor::FOREIGN_KEY);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            InvoicingAccount::FOREIGN_KEY => 'integer',
            Contractor::FOREIGN_KEY => 'integer',
            self::EXTERNAL_ID => 'string',
            self::EXTERNAL_UPDATED_AT => 'immutable_datetime',
            self::SYNCED_AT => 'immutable_datetime',
            self::SYNCED_CHECKSUM => 'string',
        ];
    }
}
