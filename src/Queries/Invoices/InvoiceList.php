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

namespace Simplysmart\Polygon\Queries\Invoices;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Simplysmart\Polygon\Database\ColumnNames;
use Simplysmart\Polygon\Database\LikePattern;
use Simplysmart\Polygon\Enums\InvoiceKind;
use Simplysmart\Polygon\Enums\InvoiceStatus;
use Simplysmart\Polygon\Invoicing\InvoicingAccounts;
use Simplysmart\Polygon\Models\Contractor;
use Simplysmart\Polygon\Models\Invoice;
use Simplysmart\Polygon\Models\Organization;

/**
 * Faktury wystawione przez organizację główną klienta (InvoicingAccounts::root()) — od najnowszej daty wystawienia,
 * najwyżej LIMIT: opcjonalnie tylko kontrahenta, w stanie, danego rodzaju (VAT, pro forma), albo z frazą w numerze (nadanym albo roboczym) lub nazwie
 * nabywcy. findByToken() — faktura tej organizacji głównej.
 */
final readonly class InvoiceList
{
    public const int LIMIT = 200;

    public function __construct(
        private InvoicingAccounts $accounts,
    ) {}

    /**
     * @return Collection<int, Invoice>
     */
    public function of(Organization $organization, ?Contractor $contractor = null, ?InvoiceStatus $status = null, ?string $term = null, ?InvoiceKind $kind = null): Collection
    {
        $query = $this->query($organization);

        if ($contractor !== null) {
            $query->where(Contractor::FOREIGN_KEY, $contractor->id);
        }

        if ($status !== null) {
            $query->where(ColumnNames::STATUS, $status->value);
        }

        if ($kind !== null) {
            $query->where(Invoice::KIND, $kind->value);
        }

        $pattern = LikePattern::contains((string) $term);

        if ($pattern !== null) {
            $query->where(static function (Builder $match) use ($pattern): void {
                $match->orWhereRaw('LOWER(' . ColumnNames::NAME . ') LIKE ?', [$pattern]);
                $match->orWhereRaw('LOWER(' . Invoice::EXTERNAL_NUMBER . ') LIKE ?', [$pattern]);
                $match->orWhereRaw('LOWER(' . Invoice::BUYER_NAME . ') LIKE ?', [$pattern]);
            });
        }

        $query->orderByDesc(Invoice::ISSUE_DATE);
        $query->orderByDesc(ColumnNames::ID);
        $query->limit(self::LIMIT);

        return $query->get();
    }

    public function findByToken(Organization $organization, string $token): ?Invoice
    {
        $query = $this->query($organization);
        $query->where(ColumnNames::TOKEN, $token);

        return $query->first();
    }

    /**
     * @return Builder<Invoice>
     */
    private function query(Organization $organization): Builder
    {
        $query = Invoice::query();
        $query->where(Organization::FOREIGN_KEY, $this->accounts->root($organization)->id);
        $query->with('contractor');

        return $query;
    }
}
