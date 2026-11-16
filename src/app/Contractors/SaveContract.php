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

use Carbon\CarbonImmutable;
use Simplysmart\Polygon\Database\ColumnNames;
use Simplysmart\Polygon\Localization\PackageText;
use Simplysmart\Polygon\Models\Contractor;
use Simplysmart\Polygon\Models\ContractorContract;

/**
 * Zapis umowy z kontrahentem: numer, data podpisania i okres obowiązywania (koniec nie przed początkiem).
 */
final readonly class SaveContract
{
    /**
     * @throws ContractorRejected Gdy umowa kończy się przed początkiem.
     */
    public function save(Contractor $contractor, string $number, ?CarbonImmutable $signedOn, CarbonImmutable $validFrom, ?CarbonImmutable $validTo, ?string $notes = null, ?ContractorContract $contract = null): ContractorContract
    {
        if ($validTo !== null && $validTo->lessThan($validFrom)) {
            throw new ContractorRejected(ContractorContract::VALID_TO, PackageText::get('contractors.contract_period_invalid'));
        }

        $contract ??= new ContractorContract();
        $contract->forceFill([
            Contractor::FOREIGN_KEY => $contractor->id,
            ColumnNames::NAME => trim($number),
            ContractorContract::SIGNED_ON => $signedOn?->toDateString(),
            ContractorContract::VALID_FROM => $validFrom->toDateString(),
            ContractorContract::VALID_TO => $validTo?->toDateString(),
            ContractorContract::NOTES => trim((string) $notes) === '' ? null : trim((string) $notes),
        ])->save();

        return $contract;
    }
}
