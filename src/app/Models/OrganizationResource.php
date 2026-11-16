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

use Simplysmart\Polygon\Enums\ResourceKind;
use Simplysmart\Polygon\Models\Relations\BelongsToOrganization;

/**
 * Zasób organizacji (`organization_resources`, docs/baza-danych.md §17): gabinet, sprzęt albo inny zasób, którego
 * właścicielem jest jedna organizacja (`organization_id`); korzystają z niego ona i jej jednostki podrzędne
 * (Queries\Organizations\OrganizationResources). `code` — krótki kod do grafiku i tablic (np. „G3”, unikalny
 * w organizacji); `name` — nazwa (np. „Gabinet 3”). Paczki stref odwołują się do zasobu kluczem obcym
 * `resource_id` (np. gabinet w grafiku lekarza).
 *
 * @property int $organization_id
 * @property ResourceKind $kind
 * @property string|null $code
 * @property-read Organization|null $organization
 */
final class OrganizationResource extends DataModel
{
    use BelongsToOrganization;

    public const string TABLE = 'organization_resources';

    /** Klucz obcy zasobu w tabelach paczek (np. gabinet w grafiku). */
    public const string FOREIGN_KEY = 'resource_id';

    public const string KIND = 'kind';

    public const string CODE = 'code';

    public const int CODE_MAX_LENGTH = 16;

    protected $table = self::TABLE;

    /**
     * Wartości domyślne kolumn: nowy zasób to aktywny gabinet.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [self::KIND => 'room', 'is_active' => true];

    /**
     * Nazwa do list i grafiku: „kod · nazwa”, a bez kodu sama nazwa.
     */
    public function label(): string
    {
        return $this->code === null || $this->code === '' ? $this->name : "{$this->code} · {$this->name}";
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [Organization::FOREIGN_KEY => 'integer', self::KIND => ResourceKind::class, self::CODE => 'string'];
    }
}
