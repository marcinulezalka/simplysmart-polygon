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

namespace Simplysmart\Polygon\Contractors;

use Simplysmart\Polygon\Identifiers\Nip;
use Simplysmart\Polygon\Localization\PackageText;
use Simplysmart\Polygon\Models\Contractor;
use Simplysmart\Polygon\Support\OptionalText;

/**
 * Zapis danych do faktury kontrahenta (`billing_*`): NIP normalizowany do samych cyfr i sprawdzany (suma kontrolna),
 * bez wymogu unikalności — ten sam nabywca może płacić za kilku kontrahentów; kod kraju wielkimi literami; puste
 * pola zapisywane jako null. Pozostałych danych kontrahenta nie zmienia.
 */
final readonly class SaveBillingData
{
    /**
     * @throws ContractorRejected Gdy NIP jest błędny (pole `billing_nip`).
     */
    public function save(Contractor $contractor, BillingData $data): Contractor
    {
        $nip = OptionalText::of($data->nip) === null ? null : Nip::normalise($data->nip);

        if ($nip !== null && ! Nip::isValid($nip)) {
            throw new ContractorRejected(Contractor::BILLING_NIP, PackageText::get('identifiers.nip_invalid'));
        }

        $countryCode = OptionalText::of($data->countryCode);

        $contractor->forceFill([
            Contractor::BILLING_NAME => OptionalText::of($data->name),
            Contractor::BILLING_NIP => $nip,
            Contractor::BILLING_STREET => OptionalText::of($data->street),
            Contractor::BILLING_POSTAL_CODE => OptionalText::of($data->postalCode),
            Contractor::BILLING_CITY => OptionalText::of($data->city),
            Contractor::BILLING_COUNTRY_CODE => $countryCode === null ? null : strtoupper($countryCode),
            Contractor::BILLING_CONTACT_NAME => OptionalText::of($data->contactName),
            Contractor::BILLING_EMAIL => OptionalText::of($data->email),
        ])->save();

        return $contractor;
    }
}
