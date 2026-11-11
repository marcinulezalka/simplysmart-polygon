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
use Simplysmart\Polygon\Contractors\ContractorRejected;
use Simplysmart\Polygon\Contractors\SaveContractor;
use Simplysmart\Polygon\Database\ColumnNames;
use Simplysmart\Polygon\Integrations\Fakturownia\ClientMapper;
use Simplysmart\Polygon\Integrations\Fakturownia\FakturowniaClient;
use Simplysmart\Polygon\Integrations\Fakturownia\FakturowniaClients;
use Simplysmart\Polygon\Integrations\Fakturownia\FakturowniaFailed;
use Simplysmart\Polygon\Localization\PackageText;
use Simplysmart\Polygon\Models\Contractor;
use Simplysmart\Polygon\Models\InvoicingAccount;
use Simplysmart\Polygon\Models\InvoicingContractorLink;
use Simplysmart\Polygon\Models\Organization;

/**
 * Dwukierunkowa synchronizacja kontrahentów organizacji z klientami Fakturowni (docs/baza-danych.md §21).
 *
 * sync() pobiera klientów stronami (pull()) i wysyła kontrahentów, których Fakturownia jeszcze nie ma albo którzy
 * zmienili się od synchronizacji (pushPending()). Klient jest dopasowywany kolejno: po powiązaniu (id klienta), po
 * tokenie kontrahenta w `external_id` klienta, po poprawnym NIP-ie wśród kontrahentów organizacji objętych kontem;
 * bez dopasowania powstaje nowy kontrahent organizacji głównej (zapis przez SaveContractor — te same reguły NIP i REGON).
 * Gdy obie strony zmieniły się od synchronizacji, wygrywa nowsza zmiana (`updated_at` kontrahenta i klienta). Po każdej
 * synchronizacji powiązanie dostaje odcisk danych (ContractorFingerprint) — kontrahent z tym odciskiem nie jest wysyłany.
 * Własne zapisy idą przy zamkniętej bramce (ContractorPushGate), więc nie zlecają wysyłki zwrotnej. Drugi klient
 * Fakturowni pasujący do kontrahenta, który ma już powiązanie (duplikat w Fakturowni), jest pomijany z wpisem w raporcie.
 * Kontrahent tylko lokalny (Contractor::isInvoicingLocalOnly(), np. pracodawca z importu historii) nie jest wysyłany.
 */
final readonly class SyncContractors
{
    /** Najwięcej stron klientów w jednym pobraniu (PER_PAGE klientów na stronę). */
    private const int MAX_PAGES = 500;

    public function __construct(
        private InvoicingAccounts $accounts,
        private FakturowniaClients $clients,
        private SaveContractor $save,
        private ContractorPushGate $gate,
    ) {}

    /**
     * @throws FakturowniaFailed Gdy Fakturownia nie odpowiada albo odrzuca dane dostępowe (błąd trafia też do konta).
     */
    public function sync(InvoicingAccount $account): ContractorSyncReport
    {
        $report = $this->pull($account);
        $this->pushPending($account, $report);

        return $report;
    }

    /**
     * @throws FakturowniaFailed
     */
    public function pull(InvoicingAccount $account, ?ContractorSyncReport $report = null): ContractorSyncReport
    {
        $report ??= new ContractorSyncReport();
        $client = $this->clients->for($account);
        $root = $account->organization;

        if ($root === null) {
            return $report;
        }

        $organizationIds = $this->accounts->organizationIds($account);

        try {
            for ($page = 1; $page <= self::MAX_PAGES; $page++) {
                $remoteClients = $client->clients($page);

                foreach ($remoteClients as $remote) {
                    $this->pullClient($account, $client, $root, $organizationIds, $remote, $report);
                }

                if (count($remoteClients) < FakturowniaClient::PER_PAGE) {
                    break;
                }
            }
        } catch (FakturowniaFailed $failed) {
            $account->forceFill([InvoicingAccount::LAST_ERROR => $failed->getMessage()])->save();

            throw $failed;
        }

        $account->forceFill([InvoicingAccount::CONTRACTORS_SYNCED_AT => CarbonImmutable::now(), InvoicingAccount::LAST_ERROR => null])->save();

        return $report;
    }

    /**
     * Jeden klient Fakturowni (np. nabywca pobranej faktury) jako kontrahent — dopasowany, zaktualizowany albo założony
     * jak w pull(); null, gdy klienta trzeba było pominąć (powód w raporcie).
     *
     * @param array<array-key, mixed> $remote
     *
     * @throws FakturowniaFailed
     */
    public function pullOne(InvoicingAccount $account, array $remote, ContractorSyncReport $report): ?Contractor
    {
        $root = $account->organization;

        return $root === null ? null : $this->pullClient($account, $this->clients->for($account), $root, $this->accounts->organizationIds($account), $remote, $report);
    }

    /**
     * Wysyła kontrahentów organizacji objętych kontem, którzy nie mają powiązania albo zmienili się od synchronizacji.
     *
     * @throws FakturowniaFailed
     */
    public function pushPending(InvoicingAccount $account, ?ContractorSyncReport $report = null): ContractorSyncReport
    {
        $report ??= new ContractorSyncReport();
        $links = [];

        foreach ($account->contractorLinks()->get() as $existing) {
            $links[$existing->contractor_id] = $existing;
        }

        $query = Contractor::query();
        $query->whereIn(Organization::FOREIGN_KEY, $this->accounts->organizationIds($account));
        $query->orderBy(ColumnNames::ID);

        foreach ($query->get() as $contractor) {
            $link = $links[$contractor->id] ?? null;

            if ($contractor->isInvoicingLocalOnly() || ($link !== null && $link->synced_checksum === ContractorFingerprint::of($contractor))) {
                continue;
            }

            try {
                $this->push($account, $contractor);
                $report->pushed++;
            } catch (FakturowniaFailed $failed) {
                if ($failed->retryable() || $failed->unauthorized()) {
                    throw $failed;
                }

                $report->skip($contractor->displayName(), $failed->getMessage());
            }
        }

        return $report;
    }

    /**
     * Zakłada albo aktualizuje klienta w Fakturowni dla kontrahenta i zapisuje powiązanie. Kontrahent bez zmian od
     * synchronizacji nie jest wysyłany. Klient bez powiązania jest szukany po tokenie kontrahenta i po NIP-ie, żeby
     * nie powstał duplikat.
     *
     * @throws FakturowniaFailed
     */
    public function push(InvoicingAccount $account, Contractor $contractor): InvoicingContractorLink
    {
        $link = $this->link($account, $contractor);
        $fingerprint = ContractorFingerprint::of($contractor);

        if ($link !== null && $link->synced_checksum === $fingerprint) {
            return $link;
        }

        $client = $this->clients->for($account);
        $payload = ClientMapper::toPayload($contractor);
        $externalId = $link === null ? $this->remoteId($client, $contractor) : $link->external_id;

        try {
            $remote = $externalId === null ? $client->createClient($payload) : $client->updateClient($externalId, $payload);
        } catch (FakturowniaFailed $failed) {
            if ($externalId === null || $failed->status !== 404) {
                throw $failed;
            }

            $remote = $client->createClient($payload);
        }

        return $this->saveLink($account, $contractor, $link, ClientMapper::id($remote) ?? $externalId ?? '', ClientMapper::updatedAt($remote));
    }

    /**
     * Kontrahent po synchronizacji klienta albo null, gdy klient został pominięty.
     *
     * @param list<int> $organizationIds
     * @param array<array-key, mixed> $remote
     *
     * @throws FakturowniaFailed
     */
    private function pullClient(InvoicingAccount $account, FakturowniaClient $client, Organization $root, array $organizationIds, array $remote, ContractorSyncReport $report): ?Contractor
    {
        $externalId = ClientMapper::id($remote);
        $name = is_string($remote['name'] ?? null) ? trim($remote['name']) : '';

        if ($externalId === null) {
            return null;
        }

        if ($name === '') {
            $report->skip('#' . $externalId, PackageText::get('invoicing.client_without_name'));

            return null;
        }

        $linked = InvoicingContractorLink::query();
        $linked->where(InvoicingAccount::FOREIGN_KEY, $account->id);
        $linked->where(InvoicingContractorLink::EXTERNAL_ID, $externalId);
        $link = $linked->first();
        $contractor = $link === null ? null : $link->contractor;
        $contractor ??= $this->match($organizationIds, $remote);
        $remoteAt = ClientMapper::updatedAt($remote);

        if ($contractor !== null) {
            $link ??= $this->link($account, $contractor);

            if ($link !== null && $link->external_id !== $externalId) {
                $report->skip($name, PackageText::get('invoicing.client_duplicate', ['contractor' => $contractor->displayName()]));

                return null;
            }

            $localChanged = $link === null || $link->synced_checksum !== ContractorFingerprint::of($contractor);
            $remoteChanged = $link === null || $remoteAt === null || $link->external_updated_at === null || $remoteAt->greaterThan($link->external_updated_at);

            if (! $remoteChanged && ! $localChanged) {
                $report->unchanged++;

                return $contractor;
            }

            if ($localChanged && (! $remoteChanged || ($remoteAt !== null && $contractor->updated_at !== null && $contractor->updated_at->greaterThan($remoteAt)))) {
                $pushed = $client->updateClient($externalId, ClientMapper::toPayload($contractor));
                $this->saveLink($account, $contractor, $link, $externalId, ClientMapper::updatedAt($pushed) ?? $remoteAt);
                $report->pushed++;

                return $contractor;
            }
        }

        try {
            $saved = $this->gate->closed(fn(): Contractor => $this->save->save($root, ClientMapper::toData($remote, $contractor), $contractor));
        } catch (ContractorRejected $rejected) {
            $report->skip($name, $rejected->getMessage());

            return null;
        }

        $this->saveLink($account, $saved, $link, $externalId, $remoteAt);
        $contractor === null ? $report->created++ : $report->updated++;

        return $saved;
    }

    /**
     * Kontrahent organizacji objętych kontem dla klienta bez powiązania: po tokenie w `external_id`, potem po NIP-ie
     * (najpierw organizacji wyżej w drzewie — niższe id organizacji).
     *
     * @param list<int> $organizationIds
     * @param array<array-key, mixed> $remote
     */
    private function match(array $organizationIds, array $remote): ?Contractor
    {
        $token = $remote['external_id'] ?? null;

        if (is_string($token) && $token !== '') {
            $byToken = Contractor::query();
            $byToken->whereIn(Organization::FOREIGN_KEY, $organizationIds);
            $byToken->where(ColumnNames::TOKEN, $token);
            $found = $byToken->first();

            if ($found !== null) {
                return $found;
            }
        }

        $nip = ClientMapper::nip($remote);

        if ($nip === null) {
            return null;
        }

        $byNip = Contractor::query();
        $byNip->whereIn(Organization::FOREIGN_KEY, $organizationIds);
        $byNip->where(Contractor::NIP, $nip);
        $byNip->orderBy(Organization::FOREIGN_KEY);

        return $byNip->first();
    }

    /**
     * Id klienta w Fakturowni dla kontrahenta bez powiązania: po tokenie kontrahenta, potem po NIP-ie.
     *
     * @throws FakturowniaFailed
     */
    private function remoteId(FakturowniaClient $client, Contractor $contractor): ?string
    {
        $remote = $client->clientByExternalId($contractor->token);

        if ($remote === null && $contractor->nip !== null) {
            $remote = $client->clientByTaxNo($contractor->nip);
            $remote = $remote !== null && ClientMapper::nip($remote) === $contractor->nip ? $remote : null;
        }

        return $remote === null ? null : ClientMapper::id($remote);
    }

    private function link(InvoicingAccount $account, Contractor $contractor): ?InvoicingContractorLink
    {
        $query = InvoicingContractorLink::query();
        $query->where(InvoicingAccount::FOREIGN_KEY, $account->id);
        $query->where(Contractor::FOREIGN_KEY, $contractor->id);

        return $query->first();
    }

    private function saveLink(InvoicingAccount $account, Contractor $contractor, ?InvoicingContractorLink $link, string $externalId, ?CarbonImmutable $remoteAt): InvoicingContractorLink
    {
        $link ??= new InvoicingContractorLink();
        $link->forceFill([
            InvoicingAccount::FOREIGN_KEY => $account->id,
            Contractor::FOREIGN_KEY => $contractor->id,
            ColumnNames::NAME => $contractor->name,
            InvoicingContractorLink::EXTERNAL_ID => $externalId,
            InvoicingContractorLink::EXTERNAL_UPDATED_AT => $remoteAt ?? $link->external_updated_at,
            InvoicingContractorLink::SYNCED_AT => CarbonImmutable::now(),
            InvoicingContractorLink::SYNCED_CHECKSUM => ContractorFingerprint::of($contractor->refresh()),
        ])->save();

        return $link;
    }
}
