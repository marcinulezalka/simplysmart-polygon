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
use Simplysmart\Polygon\Calendar\ValidityPeriod;
use Simplysmart\Polygon\Calendar\WeekCycle;
use Simplysmart\Polygon\Enums\LeaseAccess;
use Simplysmart\Polygon\Enums\LeaseMode;
use Simplysmart\Polygon\Leases\LeaseWindows;
use Simplysmart\Polygon\Models\Relations\BelongsToOrganization;

/**
 * Najem zasobu (`resource_leases`, docs/baza-danych.md §20): zasób (`resource_id`) właściciela (`organization_id`)
 * wynajęty organizacji najemcy (`lessee_organization_id`) z jej podorganizacją kooperacji
 * (`cooperation_organization_id`, Organization::IS_COOPERATION), w której jest grafik najemcy na tym zasobie. `name` —
 * numer umowy; `contractor_contract_id` — opcjonalna umowa kontrahenta po stronie właściciela; okres `valid_from`–
 * `valid_to` (bez końca: null); `windows` — okna godzinowe (Leases\LeaseWindows, bez okien: cały zasób) co
 * `week_interval` tygodni od tygodnia `valid_from` (Calendar\WeekCycle); `mode` — okna na wyłączność albo najem na żądanie
 * w wolnym czasie (Enums\LeaseMode); `access` —
 * poziomy udostępnienia kalendarza najemcy właścicielowi (Enums\LeaseAccess); `wallboard_token` — token publicznej
 * tablicy. Zapisywany tylko przez Leases\SaveLease, TerminateLease i instalator.
 *
 * @property int $organization_id
 * @property int $resource_id
 * @property int $lessee_organization_id
 * @property int $cooperation_organization_id
 * @property int|null $contractor_contract_id
 * @property CarbonImmutable|null $signed_on
 * @property CarbonImmutable $valid_from
 * @property CarbonImmutable|null $valid_to
 * @property array<array-key, mixed>|null $windows
 * @property int $week_interval
 * @property LeaseMode $mode
 * @property array<array-key, mixed>|null $access
 * @property string|null $wallboard_token
 * @property-read Organization|null $organization
 * @property-read OrganizationResource|null $resource
 * @property-read Organization|null $lessee
 * @property-read Organization|null $cooperation
 * @property-read ContractorContract|null $contract
 */
final class ResourceLease extends DataModel
{
    use BelongsToOrganization;

    public const string TABLE = 'resource_leases';

    public const string LESSEE_ID = 'lessee_organization_id';

    public const string COOPERATION_ID = 'cooperation_organization_id';

    public const string CONTRACT_ID = 'contractor_contract_id';

    /** Data zawarcia umowy — informacja o podpisaniu, bez wpływu na grafik (okres i cykl liczą się od VALID_FROM). */
    public const string SIGNED_ON = 'signed_on';

    public const string VALID_FROM = ValidityPeriod::FROM;

    public const string VALID_TO = ValidityPeriod::TO;

    public const string WINDOWS = 'windows';

    public const string WEEK_INTERVAL = 'week_interval';

    public const string MODE = 'mode';

    public const string ACCESS = 'access';

    public const string WALLBOARD_TOKEN = 'wallboard_token';

    public const int WALLBOARD_TOKEN_LENGTH = 64;

    protected $table = self::TABLE;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['is_active' => true, self::WEEK_INTERVAL => 1, self::MODE => 'windows'];

    /**
     * @return BelongsTo<OrganizationResource, $this>
     */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(OrganizationResource::class, OrganizationResource::FOREIGN_KEY);
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function lessee(): BelongsTo
    {
        return $this->belongsTo(Organization::class, self::LESSEE_ID);
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function cooperation(): BelongsTo
    {
        return $this->belongsTo(Organization::class, self::COOPERATION_ID);
    }

    /**
     * @return BelongsTo<ContractorContract, $this>
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(ContractorContract::class, self::CONTRACT_ID);
    }

    public function leaseWindows(): LeaseWindows
    {
        return LeaseWindows::fromStored($this->windows);
    }

    public function isExclusive(): bool
    {
        return $this->mode->isExclusive();
    }

    /**
     * Cykl tygodni okien umowy (od tygodnia `valid_from`).
     */
    public function cycle(): WeekCycle
    {
        return new WeekCycle($this->week_interval, $this->valid_from);
    }

    /**
     * Poziomy udostępnienia zapisane w umowie (pomija nieznane).
     *
     * @return list<LeaseAccess>
     */
    public function accessLevels(): array
    {
        $levels = [];

        foreach ($this->access ?? [] as $value) {
            $level = is_string($value) ? LeaseAccess::tryFrom($value) : null;

            if ($level !== null) {
                $levels[] = $level;
            }
        }

        return $levels;
    }

    /**
     * Czy umowa daje właścicielowi poziom $access.
     */
    public function grants(LeaseAccess $access): bool
    {
        foreach ($this->accessLevels() as $level) {
            if ($level->grants($access)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Czy okna umowy przypadają w dniu $day: umowa obowiązuje, a tydzień należy do jej cyklu.
     */
    public function occursOn(CarbonImmutable $day): bool
    {
        return $this->inForceOn($day) && $this->cycle()->occursOn($day);
    }

    /**
     * Czy umowa obowiązuje w dniu $day (aktywna i w okresie).
     */
    public function inForceOn(CarbonImmutable $day): bool
    {
        $day = $day->startOfDay();

        return $this->is_active
            && $day->greaterThanOrEqualTo($this->valid_from->startOfDay())
            && ($this->valid_to === null || $day->lessThanOrEqualTo($this->valid_to->startOfDay()));
    }

    /**
     * Czy umowa obowiązuje przez cały okres $from–$to (bez końca: null).
     */
    public function coversPeriod(CarbonImmutable $from, ?CarbonImmutable $to): bool
    {
        if (! $this->is_active || $from->startOfDay()->lessThan($this->valid_from->startOfDay())) {
            return false;
        }

        return $this->valid_to === null || ($to !== null && $to->startOfDay()->lessThanOrEqualTo($this->valid_to->startOfDay()));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            Organization::FOREIGN_KEY => 'integer',
            OrganizationResource::FOREIGN_KEY => 'integer',
            self::LESSEE_ID => 'integer',
            self::COOPERATION_ID => 'integer',
            self::CONTRACT_ID => 'integer',
            self::SIGNED_ON => 'immutable_date',
            self::VALID_FROM => 'immutable_date',
            self::VALID_TO => 'immutable_date',
            self::WINDOWS => 'array',
            self::WEEK_INTERVAL => 'integer',
            self::MODE => LeaseMode::class,
            self::ACCESS => 'array',
            self::WALLBOARD_TOKEN => 'string',
        ];
    }
}
