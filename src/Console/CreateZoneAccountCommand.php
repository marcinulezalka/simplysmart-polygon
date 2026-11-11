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

namespace Simplysmart\Polygon\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;
use Simplysmart\Polygon\Auth\AccountAlreadyExists;
use Simplysmart\Polygon\Auth\CreateZoneAccount;
use Simplysmart\Polygon\Contracts\ZoneRegistry;
use Simplysmart\Polygon\Database\ColumnNames;
use Simplysmart\Polygon\Install\OrganizationInstaller;
use Simplysmart\Polygon\Models\Organization;
use Simplysmart\Polygon\Organizations\DefaultOrganization;
use Simplysmart\Polygon\Organizations\OrganizationMembership;
use Simplysmart\Polygon\Permissions\OrganizationRoles;
use Spatie\Permission\Exceptions\RoleDoesNotExist;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * Baza komendy strefy zakładającej konto: `<strefa>:account:create {email} {name} [--role=*]
 * [--organization=<klucz>[=<rola>,…]]* [--default-organization=<klucz>] [--skip-existing]`.
 *
 * Organizacje jak `accounts[].organizations` w pliku instalacji (docs/instalacja.md): klucz organizacji
 * (`json_attributes.install_key`) i role konta tylko w niej, np.
 * `<strefa>:account:create anna@example.com "Anna Nowak" --organization=<klucz-poradni>=registration`;
 * konto zostaje członkiem (personelem) każdej z nich. Domyślny kontekst po zalogowaniu: `--default-organization`,
 * a bez niej — jedyna podana organizacja. Konto, członkostwa i role powstają w jednej transakcji: nieznany klucz albo
 * rola nie zostawia konta.
 *
 * Paczka strefy ustawia tylko `$name`, `$description` i zoneName(); walidacja, transakcja (Auth\CreateZoneAccount),
 * role spatie guarda strefy bez sprawdzania eskalacji (konsola; tak powstaje pierwszy administrator, np. `--role=hero`)
 * i komunikaty są wspólne. PIN właściciel konta ustawia przy pierwszym logowaniu.
 */
abstract class CreateZoneAccountCommand extends Command
{
    /**
     * Opcja: istniejący adres kończy się sukcesem bez zmian (powtarzalny onboarding wdrożenia).
     */
    private const string SKIP_EXISTING = 'skip-existing';

    /**
     * Nazwa strefy z rejestru (Contracts\ZoneRegistry).
     */
    abstract protected function zoneName(): string;

    /** Opcja: organizacja z rolami (`klucz=rola,rola`; można powtórzyć). */
    private const string ORGANIZATION = 'organization';

    /** Opcja: klucz domyślnej organizacji konta. */
    private const string DEFAULT_ORGANIZATION = 'default-organization';

    public function handle(
        ValidationFactory $validation,
        ZoneRegistry $zones,
        CreateZoneAccount $create,
        ConnectionInterface $db,
        OrganizationMembership $membership,
        OrganizationRoles $organizationRoles,
        DefaultOrganization $default,
    ): int {
        $input = ['email' => $this->trimmedArgument('email'), 'name' => $this->trimmedArgument('name')];
        $validator = $validation->make($input, [
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        try {
            $organizations = $this->organizations();
            $defaultKey = $this->defaultOrganizationKey($organizations);
        } catch (InvalidArgumentException $invalid) {
            $this->error($invalid->getMessage());

            return self::FAILURE;
        }

        try {
            $db->transaction(function () use ($create, $zones, $input, $organizations, $defaultKey, $membership, $organizationRoles, $default): void {
                $account = $create->handle($zones->get($this->zoneName()), $input['name'], $input['email'], $this->roles());

                foreach ($organizations as $key => ['organization' => $organization, 'roles' => $roles]) {
                    $membership->add($account, $organization);
                    $organizationRoles->assignIn($account, $roles, $organization);

                    if ($key === $defaultKey) {
                        $default->save($account, $organization);
                    }
                }
            });
        } catch (AccountAlreadyExists) {
            if ($this->option(self::SKIP_EXISTING) === true) {
                $this->info('Konto z tym adresem e-mail już istnieje — pominięte.');

                return self::SUCCESS;
            }

            $this->error('Konto z tym adresem e-mail już istnieje.');

            return self::FAILURE;
        } catch (RoleDoesNotExist $exception) {
            $this->error('Nie nadano ról, konto nie zostało założone: ' . $exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Konto założone. PIN właściciel konta ustawi przy pierwszym logowaniu.');

        return self::SUCCESS;
    }

    /**
     * @return list<InputOption>
     */
    protected function getOptions(): array
    {
        return [
            new InputOption('role', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Rola konta (można powtórzyć)'),
            new InputOption(self::ORGANIZATION, null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Organizacja z rolami konta w niej: klucz=rola,rola (można powtórzyć)'),
            new InputOption(self::DEFAULT_ORGANIZATION, null, InputOption::VALUE_REQUIRED, 'Klucz domyślnej organizacji po zalogowaniu (bez opcji — jedyna podana organizacja)'),
            new InputOption(self::SKIP_EXISTING, null, InputOption::VALUE_NONE, 'Istniejące konto nie jest błędem (onboarding wdrożenia)'),
        ];
    }

    /**
     * @return list<InputArgument>
     */
    protected function getArguments(): array
    {
        return [
            new InputArgument('email', InputArgument::REQUIRED, 'Adres e-mail'),
            new InputArgument('name', InputArgument::REQUIRED, 'Imię i nazwisko'),
        ];
    }

    /**
     * @return list<string>
     */
    private function roles(): array
    {
        return array_values(array_filter((array) $this->option('role'), static fn(mixed $role): bool => is_string($role) && $role !== ''));
    }

    /**
     * Organizacje z opcji `--organization=klucz=rola,rola` po kluczu (`json_attributes.install_key`, bez usuniętych).
     *
     * @return array<string, array{organization: Organization, roles: list<string>}>
     *
     * @throws InvalidArgumentException Gdy wartość jest pusta albo organizacji o tym kluczu nie ma.
     */
    private function organizations(): array
    {
        $organizations = [];

        foreach ((array) $this->option(self::ORGANIZATION) as $value) {
            [$key, $roles] = array_pad(explode('=', is_string($value) ? trim($value) : '', 2), 2, '');
            $key = trim($key);

            if ($key === '') {
                throw new InvalidArgumentException('Podaj organizację jako klucz=rola,rola.');
            }

            $organization = Organization::query()->where(ColumnNames::JSON_ATTRIBUTES . '->' . OrganizationInstaller::INSTALL_KEY, $key)->first()
                ?? throw new InvalidArgumentException("Nie ma organizacji o kluczu {$key} — konto nie zostało założone.");
            $organizations[$key] = [
                'organization' => $organization,
                'roles' => array_values(array_filter(array_map(trim(...), explode(',', $roles)), static fn(string $role): bool => $role !== '')),
            ];
        }

        return $organizations;
    }

    /**
     * Klucz domyślnej organizacji: z opcji (jedna z podanych) albo jedyna podana; null bez organizacji.
     *
     * @param array<string, array{organization: Organization, roles: list<string>}> $organizations
     *
     * @throws InvalidArgumentException Gdy domyślna organizacja nie jest jedną z podanych.
     */
    private function defaultOrganizationKey(array $organizations): ?string
    {
        $chosen = $this->option(self::DEFAULT_ORGANIZATION);

        if (is_string($chosen) && trim($chosen) !== '') {
            return array_key_exists(trim($chosen), $organizations)
                ? trim($chosen)
                : throw new InvalidArgumentException('Domyślna organizacja musi być jedną z podanych w --organization.');
        }

        return count($organizations) === 1 ? (string) array_key_first($organizations) : null;
    }

    private function trimmedArgument(string $name): string
    {
        $value = $this->argument($name);

        return is_string($value) ? trim($value) : '';
    }
}
