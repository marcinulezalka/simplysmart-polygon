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
use Simplysmart\Polygon\Auth\AccountAlreadyExists;
use Simplysmart\Polygon\Auth\CreateZoneAccount;
use Simplysmart\Polygon\Contracts\ZoneRegistry;
use Spatie\Permission\Exceptions\RoleDoesNotExist;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * Baza komendy strefy zakładającej konto: `<strefa>:account:create {email} {name} [--role=*] [--skip-existing]`.
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

    public function handle(ValidationFactory $validation, ZoneRegistry $zones, CreateZoneAccount $create): int
    {
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
            $create->handle($zones->get($this->zoneName()), $input['name'], $input['email'], $this->roles());
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

    private function trimmedArgument(string $name): string
    {
        $value = $this->argument($name);

        return is_string($value) ? trim($value) : '';
    }
}
