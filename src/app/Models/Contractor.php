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
use Simplysmart\Polygon\Database\ColumnNames;
use Simplysmart\Polygon\Identifiers\Nip;
use Simplysmart\Polygon\Models\Relations\BelongsToOrganization;

/**
 * Kontrahent organizacji (`contractors`, docs/baza-danych.md §19): firma, z którą organizacja pracuje — np. pracodawca
 * kierujący pracowników na badania. Dane firmy: `name`, `short_name`, `nip` (10 cyfr, unikalny w organizacji),
 * `regon`, `krs`, adres, `country_code`, `email`, `phone`, `notes`; dane do faktury (`billing_*` — pełna nazwa nabywcy,
 * NIP, adres, kraj, osoba kontaktowa i e-mail do wysyłki faktur, Contractors\SaveBillingData). Kontrahent organizacji
 * jest dostępny także w jej jednostkach podrzędnych (Queries\Contractors\ContractorSearch). Zapisywany przez
 * Contractors\SaveContractor.
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
 * @property string|null $billing_name
 * @property string|null $billing_nip
 * @property string|null $billing_street
 * @property string|null $billing_postal_code
 * @property string|null $billing_city
 * @property string|null $billing_country_code
 * @property string|null $billing_contact_name
 * @property string|null $billing_email
 * @property-read Organization|null $organization
 */
final class Contractor extends DataModel
{
    use BelongsToOrganization;

    public const string TABLE = 'contractors';

    /** Klucz ustawień systemu fakturowego w `json_attributes`. */
    public const string INVOICING_ATTRIBUTE = 'invoicing';

    /** Znacznik kontrahenta tylko lokalnego w INVOICING_ATTRIBUTE. */
    public const string LOCAL_ONLY = 'local_only';

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

    public const string BILLING_NAME = 'billing_name';

    public const string BILLING_NIP = 'billing_nip';

    public const string BILLING_STREET = 'billing_street';

    public const string BILLING_POSTAL_CODE = 'billing_postal_code';

    public const string BILLING_CITY = 'billing_city';

    public const string BILLING_COUNTRY_CODE = 'billing_country_code';

    public const string BILLING_CONTACT_NAME = 'billing_contact_name';

    public const string BILLING_EMAIL = 'billing_email';

    /** Kolumny danych do faktury (Contractors\SaveBillingData). */
    public const array BILLING_COLUMNS = [
        self::BILLING_NAME,
        self::BILLING_NIP,
        self::BILLING_STREET,
        self::BILLING_POSTAL_CODE,
        self::BILLING_CITY,
        self::BILLING_COUNTRY_CODE,
        self::BILLING_CONTACT_NAME,
        self::BILLING_EMAIL,
    ];

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
     * Czy kontrahent jest tylko lokalny — nie trafia do systemu fakturowego (`json_attributes.invoicing.local_only`,
     * np. pracodawca z importu historii bez klienta w Fakturowni; Invoicing\SyncContractors go nie wysyła).
     */
    public function isInvoicingLocalOnly(): bool
    {
        $invoicing = $this->jsonAttribute(self::INVOICING_ATTRIBUTE);

        return is_array($invoicing) && ($invoicing[self::LOCAL_ONLY] ?? false) === true;
    }

    /**
     * Oznacza kontrahenta jako tylko lokalnego (bez zapisu — zapisuje wywołujący).
     */
    public function markInvoicingLocalOnly(): void
    {
        $attributes = is_array($this->json_attributes) ? $this->json_attributes : [];
        $invoicing = is_array($attributes[self::INVOICING_ATTRIBUTE] ?? null) ? $attributes[self::INVOICING_ATTRIBUTE] : [];
        $attributes[self::INVOICING_ATTRIBUTE] = [...$invoicing, self::LOCAL_ONLY => true];
        $this->setAttribute(ColumnNames::JSON_ATTRIBUTES, $attributes);
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
     * Czy kontrahent ma choć jedno pole danych do faktury.
     */
    public function hasBillingData(): bool
    {
        foreach (self::BILLING_COLUMNS as $column) {
            $value = $this->getAttribute($column);

            if (is_string($value) && $value !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * NIP danych do faktury w postaci „XXX-XXX-XX-XX” albo null.
     */
    public function formattedBillingNip(): ?string
    {
        return $this->billing_nip === null ? null : Nip::format($this->billing_nip);
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
            ...array_fill_keys(self::BILLING_COLUMNS, 'string'),
        ];
    }
}
