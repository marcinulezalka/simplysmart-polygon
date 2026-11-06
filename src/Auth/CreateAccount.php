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

namespace Simplysmart\Polygon\Auth;

use Illuminate\Database\ConnectionInterface;
use Simplysmart\Polygon\Contracts\BlindIndex;
use Simplysmart\Polygon\Database\ColumnNames;
use Simplysmart\Polygon\Models\PolygonAccount;
use Simplysmart\Polygon\Zones\PolygonZone;
use Spatie\Permission\Exceptions\RoleDoesNotExist;

/**
 * Zakłada aktywne konto strefy `polygon` bez PIN-u (PIN ustawia właściciel przy pierwszym logowaniu), z rolami spatie
 * guarda strefy, w jednej transakcji: brak roli wycofuje całe założenie. Odpowiednik `CreateZoneAccount` z admiro
 * dla strefy bez rejestracji; wywoływane z komendy `polygon:account:create`.
 */
final readonly class CreateAccount
{
    public function __construct(
        private ConnectionInterface $db,
        private BlindIndex $index,
    ) {}

    /**
     * @param list<string> $roles Nazwy istniejących ról guarda strefy.
     * @param int|null $id Stałe id konta z puli seederów (IdSlots, 1–999), np. konto startowe z pliku instalacji;
     *                     null — kolejne id użytkowników (od 1000).
     *
     * @throws AccountAlreadyExists Gdy strefa ma już konto z tym adresem (także usunięte).
     * @throws RoleDoesNotExist Gdy którejś roli nie ma; konto nie powstaje.
     */
    public function handle(string $name, string $email, array $roles = [], ?int $id = null): PolygonAccount
    {
        $hash = $this->index->hash(EmailAddress::normalize($email));

        if (PolygonAccount::withTrashed()->where(AccountColumns::EMAIL_HASH, $hash)->exists()) {
            throw AccountAlreadyExists::inZone(PolygonZone::NAME);
        }

        return $this->db->transaction(static function () use ($name, $email, $roles, $id): PolygonAccount {
            $account = new PolygonAccount();
            $account->forceFill([
                ColumnNames::ID => $id,
                AccountColumns::NAME => $name,
                AccountColumns::EMAIL => $email,
                AccountColumns::IS_ACTIVE => true,
            ])->save();

            if ($roles !== []) {
                $account->assignRole($roles);
            }

            return $account;
        });
    }
}
