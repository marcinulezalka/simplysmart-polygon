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

namespace Simplysmart\Polygon\Invoicing;

use Carbon\CarbonImmutable;
use Closure;
use Simplysmart\Polygon\Integrations\Fakturownia\FakturowniaFailed;
use Simplysmart\Polygon\Models\InvoicingAccount;

/**
 * Pobranie stronami z Fakturowni dla konta: strony od 1 do MAX_PAGES, dopóki $page() zwraca true. Błąd Fakturowni
 * trafia do konta (`last_error`) i leci dalej; po sukcesie konto dostaje chwilę synchronizacji w $syncedAtColumn
 * i czysty `last_error`.
 */
final readonly class PagedAccountSync
{
    /** Najwięcej stron w jednym pobraniu (FakturowniaClient::PER_PAGE pozycji na stronę). */
    public const int MAX_PAGES = 500;

    /**
     * @param Closure(int): bool $page Pobiera i przetwarza stronę o numerze; false — koniec pobrania.
     * @param CarbonImmutable|null $syncedAt Chwila zapisywana po sukcesie; null — chwila zakończenia.
     *
     * @throws FakturowniaFailed
     */
    public function run(InvoicingAccount $account, string $syncedAtColumn, Closure $page, ?CarbonImmutable $syncedAt = null): void
    {
        try {
            $number = 1;

            while ($number <= self::MAX_PAGES && $page($number)) {
                $number++;
            }
        } catch (FakturowniaFailed $failed) {
            $account->forceFill([InvoicingAccount::LAST_ERROR => $failed->getMessage()])->save();

            throw $failed;
        }

        $account->forceFill([$syncedAtColumn => $syncedAt ?? CarbonImmutable::now(), InvoicingAccount::LAST_ERROR => null])->save();
    }
}
