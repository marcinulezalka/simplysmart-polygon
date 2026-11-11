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

use Carbon\CarbonImmutable;
use Simplysmart\Polygon\Contractors\ContractorData;
use Simplysmart\Polygon\Identifiers\Nip;
use Simplysmart\Polygon\Models\Contractor;
use Throwable;

/**
 * Tłumaczenie kontrahenta polygon na klienta Fakturowni i z powrotem. Pola: `name`, `shortcut` ↔ nazwa skrócona,
 * `tax_no` ↔ NIP, `register_number` ↔ REGON, `street`, `post_code`, `city`, `country` (kod alpha-2), `email`, `phone`,
 * `note` ↔ uwagi; `external_id` klienta to token kontrahenta (po nim Fakturownia odnajduje klienta bez NIP-u).
 * Z Fakturowni przychodzą dane niepewne: NIP bywa z prefiksem kraju i myślnikami, a dla firm zagranicznych nie jest
 * polskim NIP-em — nip() zwraca go tylko, gdy przechodzi sumę kontrolną. Pola dłuższe niż kolumny `contractors`:
 * opisowe (nazwy, ulica, miasto) są przycinane, a identyfikatory (REGON, kod pocztowy, telefon, e-mail) pomijane —
 * ucięty identyfikator byłby błędny.
 */
final class ClientMapper
{
    /** Najdłuższa nazwa i pozostałe pola tekstowe (`string` bez długości). */
    private const int TEXT_LENGTH = 255;

    private const int SHORT_NAME_LENGTH = 64;

    private const int REGON_LENGTH = 14;

    private const int POSTAL_CODE_LENGTH = 16;

    private const int PHONE_LENGTH = 32;

    /**
     * @return array<string, mixed>
     */
    public static function toPayload(Contractor $contractor): array
    {
        return [
            'name' => $contractor->name,
            'shortcut' => $contractor->short_name,
            'tax_no' => $contractor->nip,
            'register_number' => $contractor->regon,
            'street' => $contractor->street,
            'post_code' => $contractor->postal_code,
            'city' => $contractor->city,
            'country' => $contractor->country_code,
            'email' => $contractor->email,
            'phone' => $contractor->phone,
            'note' => $contractor->notes,
            'company' => true,
            'external_id' => $contractor->token,
        ];
    }

    /**
     * Dane kontrahenta z klienta Fakturowni; $current — kontrahent aktualizowany (zostają jego pola, których
     * Fakturownia nie ma: KRS i aktywność).
     *
     * @param array<array-key, mixed> $client
     */
    public static function toData(array $client, ?Contractor $current = null): ContractorData
    {
        return new ContractorData(
            name: self::cut($client, 'name', self::TEXT_LENGTH) ?? '',
            shortName: self::cut($client, 'shortcut', self::SHORT_NAME_LENGTH),
            nip: self::nip($client),
            regon: self::fitting($client, 'register_number', self::REGON_LENGTH),
            krs: $current?->krs,
            street: self::cut($client, 'street', self::TEXT_LENGTH),
            postalCode: self::fitting($client, 'post_code', self::POSTAL_CODE_LENGTH),
            city: self::cut($client, 'city', self::TEXT_LENGTH),
            countryCode: self::country($client),
            email: self::fitting($client, 'email', self::TEXT_LENGTH),
            phone: self::fitting($client, 'phone', self::PHONE_LENGTH) ?? self::fitting($client, 'mobile_phone', self::PHONE_LENGTH),
            notes: self::text($client, 'note'),
            active: $current === null ? true : $current->is_active,
        );
    }

    /**
     * Id klienta w Fakturowni jako tekst albo null, gdy odpowiedź go nie ma.
     *
     * @param array<array-key, mixed> $client
     */
    public static function id(array $client): ?string
    {
        $id = $client['id'] ?? null;

        return is_int($id) || (is_string($id) && $id !== '') ? (string) $id : null;
    }

    /**
     * Poprawny polski NIP klienta (same cyfry) albo null.
     *
     * @param array<array-key, mixed> $client
     */
    public static function nip(array $client): ?string
    {
        $taxNo = self::text($client, 'tax_no');

        if ($taxNo === null) {
            return null;
        }

        $nip = Nip::normalise($taxNo);

        return Nip::isValid($nip) ? $nip : null;
    }

    /**
     * Chwila ostatniej zmiany klienta w Fakturowni albo null.
     *
     * @param array<array-key, mixed> $client
     */
    public static function updatedAt(array $client): ?CarbonImmutable
    {
        $value = self::text($client, 'updated_at');

        try {
            return $value === null ? null : CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param array<array-key, mixed> $client
     */
    private static function country(array $client): ?string
    {
        $country = self::text($client, 'country');

        return $country !== null && preg_match('/^[A-Za-z]{2}$/', $country) === 1 ? strtoupper($country) : null;
    }

    /**
     * Pole tekstowe przycięte do $length znaków (bez spacji na końcu po przycięciu).
     *
     * @param array<array-key, mixed> $client
     */
    private static function cut(array $client, string $key, int $length): ?string
    {
        $value = self::text($client, $key);

        return $value === null ? null : rtrim(mb_substr($value, 0, $length));
    }

    /**
     * Pole tekstowe albo null, gdy jest dłuższe niż $length znaków.
     *
     * @param array<array-key, mixed> $client
     */
    private static function fitting(array $client, string $key, int $length): ?string
    {
        $value = self::text($client, $key);

        return $value === null || mb_strlen($value) > $length ? null : $value;
    }

    /**
     * @param array<array-key, mixed> $client
     */
    private static function text(array $client, string $key): ?string
    {
        $value = $client[$key] ?? null;
        $value = is_string($value) || is_int($value) ? trim((string) $value) : '';

        return $value === '' ? null : $value;
    }
}
