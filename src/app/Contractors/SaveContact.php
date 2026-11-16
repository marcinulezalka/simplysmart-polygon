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

use Illuminate\Database\ConnectionInterface;
use Simplysmart\Polygon\Database\ColumnNames;
use Simplysmart\Polygon\Models\Contractor;
use Simplysmart\Polygon\Models\ContractorContact;

/**
 * Zapis osoby kontaktowej kontrahenta w transakcji: kontakt oznaczony jako główny zdejmuje to oznaczenie z pozostałych
 * kontaktów tego kontrahenta (jeden kontakt główny).
 */
final readonly class SaveContact
{
    public function __construct(
        private ConnectionInterface $db,
    ) {}

    public function save(Contractor $contractor, string $name, ?string $role, ?string $phone, ?string $email, bool $primary, ?ContractorContact $contact = null): ContractorContact
    {
        return $this->db->transaction(function () use ($contractor, $name, $role, $phone, $email, $primary, $contact): ContractorContact {
            if ($primary) {
                $others = ContractorContact::query()->where(Contractor::FOREIGN_KEY, $contractor->id);

                if ($contact !== null && $contact->exists) {
                    $others->whereKeyNot($contact->id);
                }

                foreach ($others->where(ContractorContact::IS_PRIMARY, true)->get() as $other) {
                    $other->forceFill([ContractorContact::IS_PRIMARY => false])->save();
                }
            }

            $contact ??= new ContractorContact();
            $contact->forceFill([
                Contractor::FOREIGN_KEY => $contractor->id,
                ColumnNames::NAME => trim($name),
                ContractorContact::ROLE => self::text($role),
                ContractorContact::PHONE => self::text($phone),
                ContractorContact::EMAIL => self::text($email),
                ContractorContact::IS_PRIMARY => $primary,
            ])->save();

            return $contact;
        });
    }

    private static function text(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
