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
use Illuminate\Database\ConnectionInterface;
use Simplysmart\Polygon\Contractors\ContractorRejected;
use Simplysmart\Polygon\Contractors\SaveContractor;
use Simplysmart\Polygon\Database\ColumnNames;
use Simplysmart\Polygon\Integrations\Fakturownia\ClientMapper;
use Simplysmart\Polygon\Integrations\Fakturownia\FakturowniaClient;
use Simplysmart\Polygon\Integrations\Fakturownia\FakturowniaClients;
use Simplysmart\Polygon\Integrations\Fakturownia\FakturowniaFailed;
use Simplysmart\Polygon\Integrations\Fakturownia\RemoteInvoice;
use Simplysmart\Polygon\Integrations\Fakturownia\RemoteInvoiceMapper;
use Simplysmart\Polygon\Integrations\Fakturownia\RemoteInvoiceRejected;
use Simplysmart\Polygon\Localization\PackageText;
use Simplysmart\Polygon\Models\Contractor;
use Simplysmart\Polygon\Models\Invoice;
use Simplysmart\Polygon\Models\InvoiceItem;
use Simplysmart\Polygon\Models\InvoicingAccount;
use Simplysmart\Polygon\Models\InvoicingContractorLink;
use Simplysmart\Polygon\Models\Organization;

/**
 * Pobranie faktur VAT i pro forma z Fakturowni do kartotek kontrahentów (docs/baza-danych.md §21).
 *
 * Zakres: faktury wystawione od 1 stycznia poprzedniego roku. Fakturownia nie filtruje po dacie zmiany, więc lista idzie
 * od ostatnio zmienionych, a pobranie przyrostowe (konto ma `invoices_synced_at`) kończy się na pierwszej fakturze
 * niezmienionej od poprzedniego pobrania (z zapasem SINCE_MARGIN_MINUTES na różnicę zegarów).
 *
 * Każda faktura z Fakturowni:
 * - wystawiona w aplikacji (jej `oid` to token lokalnej faktury albo zgadza się `external_id`) — dostaje tylko stan
 *   z Fakturowni (`external_status`, `paid_at`, `external_updated_at`) i brakujące identyfikatory; jej treść zostaje;
 * - pobrana wcześniej — jest aktualizowana z pozycjami, gdy zmieniła się w Fakturowni;
 * - nowa — powstaje jako faktura organizacji głównej ze źródłem Fakturownia, w stanie „wysłana”.
 * Nabywca to kontrahent powiązany z klientem faktury, potem kontrahent o NIP-ie nabywcy, a bez nich — nowy kontrahent
 * z klienta Fakturowni (SyncContractors::pullOne()) albo, gdy faktura nie ma klienta, z danych nabywcy na fakturze
 * (zapis przy zamkniętej bramce — nie wraca do Fakturowni). Faktura, której nie da się zapisać, trafia do raportu.
 */
final readonly class ImportInvoices
{
    /** Najwięcej stron faktur w jednym pobraniu (PER_PAGE faktur na stronę). */
    private const int MAX_PAGES = 500;

    /** Zapas przy porównaniu chwili zmiany z poprzednim pobraniem (różnica zegarów aplikacji i Fakturowni). */
    private const int SINCE_MARGIN_MINUTES = 10;

    public function __construct(
        private InvoicingAccounts $accounts,
        private FakturowniaClients $clients,
        private SyncContractors $contractors,
        private SaveContractor $save,
        private ContractorPushGate $gate,
        private ConnectionInterface $db,
    ) {}

    /**
     * Pierwsza data wystawienia faktur pobieranych z Fakturowni: 1 stycznia poprzedniego roku.
     */
    public static function firstIssueDate(?CarbonImmutable $now = null): CarbonImmutable
    {
        return ($now ?? CarbonImmutable::now())->startOfYear()->subYear();
    }

    /**
     * @throws FakturowniaFailed Gdy Fakturownia nie odpowiada albo odrzuca dane dostępowe (błąd trafia też do konta).
     */
    public function import(InvoicingAccount $account, ?InvoiceImportReport $report = null): InvoiceImportReport
    {
        $report ??= new InvoiceImportReport();
        $root = $account->organization;

        if ($root === null) {
            return $report;
        }

        $startedAt = CarbonImmutable::now();
        $since = $account->invoices_synced_at?->subMinutes(self::SINCE_MARGIN_MINUTES);
        $client = $this->clients->for($account);
        $organizationIds = $this->accounts->organizationIds($account);

        try {
            for ($page = 1; $page <= self::MAX_PAGES; $page++) {
                $remoteInvoices = $client->invoices($page, self::firstIssueDate($startedAt), $startedAt->addYear());

                foreach ($remoteInvoices as $remote) {
                    $updatedAt = RemoteInvoiceMapper::status($remote)[Invoice::EXTERNAL_UPDATED_AT];

                    if ($since !== null && $updatedAt instanceof CarbonImmutable && ! $updatedAt->greaterThan($since)) {
                        break 2;
                    }

                    $this->importOne($account, $client, $root, $organizationIds, $remote, $report);
                }

                if (count($remoteInvoices) < FakturowniaClient::PER_PAGE) {
                    break;
                }
            }
        } catch (FakturowniaFailed $failed) {
            $account->forceFill([InvoicingAccount::LAST_ERROR => $failed->getMessage()])->save();

            throw $failed;
        }

        $account->forceFill([InvoicingAccount::INVOICES_SYNCED_AT => $startedAt, InvoicingAccount::LAST_ERROR => null])->save();

        return $report;
    }

    /**
     * @param list<int> $organizationIds
     * @param array<array-key, mixed> $remote
     *
     * @throws FakturowniaFailed
     */
    private function importOne(InvoicingAccount $account, FakturowniaClient $client, Organization $root, array $organizationIds, array $remote, InvoiceImportReport $report): void
    {
        $label = is_scalar($remote['number'] ?? null) && (string) $remote['number'] !== '' ? (string) $remote['number'] : '#' . (is_scalar($remote['id'] ?? null) ? (string) $remote['id'] : '?');

        try {
            $invoice = RemoteInvoiceMapper::read($remote, $account->subdomain);
        } catch (RemoteInvoiceRejected $rejected) {
            $report->skip($label, $rejected->getMessage());

            return;
        }

        $existing = $this->existing($organizationIds, $invoice);

        if ($existing !== null && ! $existing->isImported()) {
            $this->refreshLocal($existing, $invoice, $remote) ? $report->updated++ : $report->unchanged++;

            return;
        }

        if ($existing !== null && $existing->external_updated_at !== null && $invoice->updatedAt !== null && ! $invoice->updatedAt->greaterThan($existing->external_updated_at)) {
            $report->unchanged++;

            return;
        }

        $contractor = ($existing === null ? null : $existing->contractor) ?? $this->buyer($account, $client, $root, $organizationIds, $invoice, $label, $report);

        if ($contractor === null) {
            return;
        }

        $this->save($existing, $root, $contractor, $invoice);
        $existing === null ? $report->created++ : $report->updated++;
    }

    /**
     * Faktura lokalna odpowiadająca fakturze z Fakturowni: po `external_id` albo po naszym `oid` (token faktury).
     *
     * @param list<int> $organizationIds
     */
    private function existing(array $organizationIds, RemoteInvoice $invoice): ?Invoice
    {
        $query = Invoice::query();
        $query->whereIn(Organization::FOREIGN_KEY, $organizationIds);
        $query->where(Invoice::EXTERNAL_ID, $invoice->externalId);
        $found = $query->first();

        if ($found !== null || $invoice->oid === null) {
            return $found;
        }

        $byOid = Invoice::query();
        $byOid->whereIn(Organization::FOREIGN_KEY, $organizationIds);
        $byOid->where(ColumnNames::TOKEN, $invoice->oid);

        return $byOid->first();
    }

    /**
     * Stan z Fakturowni i brakujące identyfikatory faktury wystawionej w aplikacji; true, gdy coś się zmieniło.
     *
     * @param array<array-key, mixed> $remote
     */
    private function refreshLocal(Invoice $local, RemoteInvoice $invoice, array $remote): bool
    {
        $local->forceFill(RemoteInvoiceMapper::status($remote));

        foreach ([Invoice::EXTERNAL_ID, Invoice::EXTERNAL_TOKEN, Invoice::EXTERNAL_NUMBER, Invoice::EXTERNAL_URL] as $column) {
            if ($local->getAttribute($column) === null && $invoice->attributes[$column] !== null) {
                $local->setAttribute($column, $invoice->attributes[$column]);
            }
        }

        if ($local->external_number !== null && str_starts_with($local->name, IssueInvoice::DRAFT_PREFIX)) {
            $local->setAttribute(ColumnNames::NAME, $local->external_number);
        }

        if (! $local->isDirty()) {
            return false;
        }

        $local->save();

        return true;
    }

    /**
     * Kontrahent nabywcy: powiązany z klientem faktury, o NIP-ie nabywcy, a bez nich — założony z klienta Fakturowni
     * albo z danych nabywcy na fakturze; null, gdy nie da się go ustalić (powód w raporcie).
     *
     * @param list<int> $organizationIds
     *
     * @throws FakturowniaFailed
     */
    private function buyer(InvoicingAccount $account, FakturowniaClient $client, Organization $root, array $organizationIds, RemoteInvoice $invoice, string $label, InvoiceImportReport $report): ?Contractor
    {
        if ($invoice->clientId !== null) {
            $linked = InvoicingContractorLink::query();
            $linked->where(InvoicingAccount::FOREIGN_KEY, $account->id);
            $linked->where(InvoicingContractorLink::EXTERNAL_ID, $invoice->clientId);
            $contractor = $linked->first()?->contractor;

            if ($contractor !== null) {
                return $contractor;
            }
        }

        $nip = ClientMapper::nip($invoice->buyer);

        if ($nip !== null) {
            $byNip = Contractor::query();
            $byNip->whereIn(Organization::FOREIGN_KEY, $organizationIds);
            $byNip->where(Contractor::NIP, $nip);
            $byNip->orderBy(Organization::FOREIGN_KEY);
            $contractor = $byNip->first();

            if ($contractor !== null) {
                return $contractor;
            }
        }

        return $invoice->clientId !== null ? $this->buyerFromClient($account, $client, $invoice->clientId, $label, $report) : $this->buyerFromInvoice($root, $invoice, $label, $report);
    }

    /**
     * @throws FakturowniaFailed
     */
    private function buyerFromClient(InvoicingAccount $account, FakturowniaClient $client, string $clientId, string $label, InvoiceImportReport $report): ?Contractor
    {
        try {
            $remote = $client->clientById($clientId);
        } catch (FakturowniaFailed $failed) {
            if ($failed->status !== 404) {
                throw $failed;
            }

            $report->skip($label, PackageText::get('invoicing.invoice_client_missing'));

            return null;
        }

        $contractors = new ContractorSyncReport();
        $contractor = $this->contractors->pullOne($account, $remote, $contractors);

        if ($contractor === null) {
            $report->skip($label, $contractors->skipped[0]['reason'] ?? PackageText::get('invoicing.invoice_buyer_missing'));

            return null;
        }

        $report->contractorsCreated += $contractors->created;

        return $contractor;
    }

    private function buyerFromInvoice(Organization $root, RemoteInvoice $invoice, string $label, InvoiceImportReport $report): ?Contractor
    {
        if (($invoice->buyer['name'] ?? null) === null) {
            $report->skip($label, PackageText::get('invoicing.invoice_buyer_missing'));

            return null;
        }

        try {
            $contractor = $this->gate->closed(fn(): Contractor => $this->save->save($root, ClientMapper::toData($invoice->buyer)));
        } catch (ContractorRejected $rejected) {
            $report->skip($label, $rejected->getMessage());

            return null;
        }

        $report->contractorsCreated++;

        return $contractor;
    }

    private function save(?Invoice $existing, Organization $root, Contractor $contractor, RemoteInvoice $invoice): void
    {
        $this->db->transaction(static function () use ($existing, $root, $contractor, $invoice): void {
            $model = $existing ?? new Invoice();
            $model->forceFill([
                ...$invoice->attributes,
                Organization::FOREIGN_KEY => $existing === null ? $root->id : $existing->organization_id,
                Contractor::FOREIGN_KEY => $contractor->id,
                Invoice::SENT_AT => $model->sent_at ?? CarbonImmutable::now(),
            ])->save();

            if ($existing !== null) {
                InvoiceItem::query()->where(Invoice::FOREIGN_KEY, $model->id)->delete();
            }

            foreach ($invoice->items as $item) {
                (new InvoiceItem())->forceFill([...$item, Invoice::FOREIGN_KEY => $model->id])->save();
            }
        });
    }
}
