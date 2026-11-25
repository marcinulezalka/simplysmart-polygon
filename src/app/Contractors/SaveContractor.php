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

use Simplysmart\Polygon\Support\OptionalText;
use Simplysmart\Polygon\Database\ColumnNames;
use Simplysmart\Polygon\Identifiers\Nip;
use Simplysmart\Polygon\Identifiers\Regon;
use Simplysmart\Polygon\Localization\PackageText;
use Simplysmart\Polygon\Models\Contractor;
use Simplysmart\Polygon\Models\Organization;

/**
 * Zapis kontrahenta organizacji: NIP i REGON normalizowane do samych cyfr i sprawdzane (suma kontrolna), NIP unikalny
 * w organizacji; kod kraju wielkimi literami. Odmowa to ContractorRejected z polem formularza.
 */
final readonly class SaveContractor
{
    /**
     * @throws ContractorRejected Gdy NIP albo REGON jest błędny, albo NIP ma już inny kontrahent organizacji.
     */
    public function save(Organization $organization, ContractorData $data, ?Contractor $contractor = null): Contractor
    {
        $nip = $data->nip === null || trim($data->nip) === '' ? null : Nip::normalise($data->nip);
        $regon = Regon::normalise($data->regon);

        if ($nip !== null && ! Nip::isValid($nip)) {
            throw new ContractorRejected(Contractor::NIP, PackageText::get('identifiers.nip_invalid'));
        }

        if ($regon !== null && ! Regon::isValid($regon)) {
            throw new ContractorRejected(Contractor::REGON, PackageText::get('identifiers.regon_invalid'));
        }

        if ($nip !== null && $this->nipTaken($organization, $nip, $contractor)) {
            throw new ContractorRejected(Contractor::NIP, PackageText::get('contractors.nip_taken'));
        }

        $contractor ??= new Contractor();
        $contractor->forceFill([
            Organization::FOREIGN_KEY => $contractor->exists ? $contractor->organization_id : $organization->id,
            ColumnNames::NAME => trim($data->name),
            ColumnNames::IS_ACTIVE => $data->active,
            Contractor::SHORT_NAME => OptionalText::of($data->shortName),
            Contractor::NIP => $nip,
            Contractor::REGON => $regon,
            Contractor::KRS => OptionalText::of($data->krs),
            Contractor::STREET => OptionalText::of($data->street),
            Contractor::POSTAL_CODE => OptionalText::of($data->postalCode),
            Contractor::CITY => OptionalText::of($data->city),
            Contractor::COUNTRY_CODE => OptionalText::of($data->countryCode) === null ? null : strtoupper(trim((string) $data->countryCode)),
            Contractor::EMAIL => OptionalText::of($data->email),
            Contractor::PHONE => OptionalText::of($data->phone),
            Contractor::NOTES => OptionalText::of($data->notes),
        ])->save();

        return $contractor;
    }

    private function nipTaken(Organization $organization, string $nip, ?Contractor $contractor): bool
    {
        $query = Contractor::withTrashed();
        $query->where(Organization::FOREIGN_KEY, $contractor !== null && $contractor->exists ? $contractor->organization_id : $organization->id);
        $query->where(Contractor::NIP, $nip);

        if ($contractor !== null && $contractor->exists) {
            $query->whereKeyNot($contractor->id);
        }

        return $query->exists();
    }
}
