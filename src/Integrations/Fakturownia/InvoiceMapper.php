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

use Simplysmart\Polygon\Models\Invoice;
use Simplysmart\Polygon\Models\InvoiceItem;

/**
 * Faktura polygon jako obiekt `invoice` API Fakturowni: rodzaj, daty, forma i termin płatności, waluta, migawka
 * sprzedawcy i nabywcy, `client_id` (klient powiązany z kontrahentem), `department_id` (dział konta, gdy ustawiony),
 * pozycje z ceną netto i stawką w formacie TaxRateCode. `number` jest pusty — numer nadaje Fakturownia. `oid` to token
 * faktury, a `oid_unique` każe Fakturowni odrzucić drugą fakturę z tym samym `oid` (ponowiona wysyłka nie dubluje).
 * Odpowiedź czyta externalId(), externalToken(), number() i url().
 */
final class InvoiceMapper
{
    /**
     * @return array<string, mixed>
     */
    public static function toPayload(Invoice $invoice, ?string $clientId, ?string $departmentId): array
    {
        return array_filter([
            'kind' => $invoice->kind->value,
            'number' => null,
            'issue_date' => $invoice->issue_date->toDateString(),
            'sell_date' => $invoice->sell_date->toDateString(),
            'payment_to' => $invoice->payment_due?->toDateString(),
            'payment_type' => $invoice->payment_method->value,
            'currency' => $invoice->currency_code,
            'lang' => 'pl',
            'oid' => $invoice->token,
            'oid_unique' => 'yes',
            'client_id' => $clientId === null ? null : (int) $clientId,
            'department_id' => $departmentId === null ? null : (int) $departmentId,
            'seller_name' => $invoice->seller_name,
            'seller_tax_no' => $invoice->seller_nip,
            'seller_street' => $invoice->seller_street,
            'seller_post_code' => $invoice->seller_postal_code,
            'seller_city' => $invoice->seller_city,
            'seller_country' => $invoice->seller_country_code,
            'buyer_name' => $invoice->buyer_name,
            'buyer_tax_no' => $invoice->buyer_nip,
            'buyer_street' => $invoice->buyer_street,
            'buyer_post_code' => $invoice->buyer_postal_code,
            'buyer_city' => $invoice->buyer_city,
            'buyer_country' => $invoice->buyer_country_code,
            'description' => $invoice->notes,
            'positions' => $invoice->items->map(static fn(InvoiceItem $item): array => array_filter([
                'name' => $item->name,
                'quantity' => (float) $item->quantity,
                'quantity_unit' => $item->unit,
                'price_net' => (float) $item->unit_price_net,
                'total_price_net' => (float) $item->net,
                'total_price_gross' => (float) $item->gross,
                'tax' => TaxRateCode::of($item->tax, $item->tax_percent),
            ], static fn(mixed $value): bool => $value !== null))->values()->all(),
        ], static fn(mixed $value, string $key): bool => $value !== null || $key === 'number', ARRAY_FILTER_USE_BOTH);
    }

    /**
     * @param array<array-key, mixed> $response
     */
    public static function externalId(array $response): ?string
    {
        return self::text($response, 'id');
    }

    /**
     * @param array<array-key, mixed> $response
     */
    public static function externalToken(array $response): ?string
    {
        return self::text($response, 'token');
    }

    /**
     * @param array<array-key, mixed> $response
     */
    public static function number(array $response): ?string
    {
        return self::text($response, 'number');
    }

    /**
     * Publiczny adres faktury w Fakturowni (z tokenu faktury) albo null.
     *
     * @param array<array-key, mixed> $response
     */
    public static function url(array $response, string $subdomain): ?string
    {
        $token = self::externalToken($response);

        return $token === null ? null : 'https://' . $subdomain . '.fakturownia.pl/invoice/' . rawurlencode($token);
    }

    /**
     * @param array<array-key, mixed> $response
     */
    private static function text(array $response, string $key): ?string
    {
        $value = $response[$key] ?? null;
        $value = is_string($value) || is_int($value) ? trim((string) $value) : '';

        return $value === '' ? null : $value;
    }
}
