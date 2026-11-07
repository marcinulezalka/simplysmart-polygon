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

use Illuminate\Database\Eloquent\Relations\HasMany;
use Simplysmart\Polygon\Identifiers\Nip;
use Simplysmart\Polygon\Models\Relations\BelongsToOrganization;

/**
 * Kontrahent organizacji (`contractors`, docs/baza-danych.md §19): firma, z którą organizacja pracuje — np. pracodawca
 * kierujący pracowników na badania. Dane firmy: `name`, `short_name`, `nip` (10 cyfr, unikalny w organizacji),
 * `regon`, `krs`, adres, `country_code`, `email`, `phone`, `notes`. Kontrahent organizacji jest dostępny także
 * w jej jednostkach podrzędnych (Queries\Contractors\ContractorSearch). Zapisywany przez Contractors\SaveContractor.
 *
 * @property int $organization_id
 * @property string|null $short_name
 * @property string|null $nip
 * @property string|null $regon
 * @property string|null $krs
 * @property string|null $street
 * @property string|null $postal_code
 * @property string|null $city
 * @property string|null $country_code
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $notes
 * @property-read Organization|null $organization
 */
final class Contractor extends DataModel
{
    use BelongsToOrganization;

    public const string TABLE = 'contractors';

    /** Klucz obcy kontrahenta w tabelach kontaktów, umów i paczek stref (np. stanowiska, zatrudnienia). */
    public const string FOREIGN_KEY = 'contractor_id';

    public const string SHORT_NAME = 'short_name';

    public const string NIP = 'nip';

    public const string REGON = 'regon';

    public const string KRS = 'krs';

    public const string STREET = 'street';

    public const string POSTAL_CODE = 'postal_code';

    public const string CITY = 'city';

    public const string COUNTRY_CODE = 'country_code';

    public const string EMAIL = 'email';

    public const string PHONE = 'phone';

    public const string NOTES = 'notes';

    protected $table = self::TABLE;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['is_active' => true];

    /**
     * @return HasMany<ContractorContact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(ContractorContact::class, self::FOREIGN_KEY);
    }

    /**
     * @return HasMany<ContractorContract, $this>
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(ContractorContract::class, self::FOREIGN_KEY);
    }

    /**
     * Nazwa do list: skrócona, a bez niej pełna.
     */
    public function displayName(): string
    {
        return $this->short_name === null || $this->short_name === '' ? $this->name : $this->short_name;
    }

    /**
     * NIP w postaci „XXX-XXX-XX-XX” albo null.
     */
    public function formattedNip(): ?string
    {
        return $this->nip === null ? null : Nip::format($this->nip);
    }

    /**
     * Adres w jednym wierszu („ulica, kod miasto”), bez pustych części.
     */
    public function address(): string
    {
        return trim(implode(', ', array_filter([$this->street, trim(($this->postal_code ?? '') . ' ' . ($this->city ?? ''))], static fn(?string $part): bool => $part !== null && trim($part) !== '')));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            Organization::FOREIGN_KEY => 'integer',
            self::SHORT_NAME => 'string',
            self::NIP => 'string',
            self::REGON => 'string',
            self::KRS => 'string',
            self::STREET => 'string',
            self::POSTAL_CODE => 'string',
            self::CITY => 'string',
            self::COUNTRY_CODE => 'string',
            self::EMAIL => 'string',
            self::PHONE => 'string',
            self::NOTES => 'string',
        ];
    }
}
