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
use Simplysmart\Polygon\Enums\InvoicingSyncTrigger;
use Throwable;

/**
 * Raport jednej synchronizacji konta z systemem fakturowym (SyncInvoicing): tryb (automatyczna z harmonogramu albo na
 * żądanie), początek i koniec, wynik kontrahentów i faktur, liczba faktur z kolejki zleconych do wysyłki i błąd, który
 * przerwał synchronizację. toArray() to postać zapisywana w `invoicing_accounts.last_sync_report`; ekran strefy czyta
 * ją przez fromArray().
 */
final class InvoicingSyncReport
{
    /** Najwięcej pominiętych wpisów zapisywanych w raporcie konta (na kontrahentów i na faktury osobno). */
    public const int MAX_SKIPPED = 50;

    public ?CarbonImmutable $finishedAt = null;

    public int $invoicesQueued = 0;

    public ?string $error = null;

    public function __construct(
        public readonly InvoicingSyncTrigger $trigger,
        public readonly CarbonImmutable $startedAt,
        public ContractorSyncReport $contractors = new ContractorSyncReport(),
        public InvoiceImportReport $invoices = new InvoiceImportReport(),
    ) {}

    /**
     * Czy synchronizacja przeszła bez błędu.
     */
    public function succeeded(): bool
    {
        return $this->error === null;
    }

    /**
     * @return array{trigger: string, started_at: string, finished_at: string|null, error: string|null, invoices_queued: int, contractors: array{created: int, updated: int, pushed: int, unchanged: int, skipped: list<array{name: string, reason: string}>}, invoices: array{created: int, updated: int, unchanged: int, contractors_created: int, skipped: list<array{name: string, reason: string}>}}
     */
    public function toArray(): array
    {
        return [
            'trigger' => $this->trigger->value,
            'started_at' => $this->startedAt->toIso8601String(),
            'finished_at' => $this->finishedAt?->toIso8601String(),
            'error' => $this->error,
            'invoices_queued' => $this->invoicesQueued,
            'contractors' => [
                'created' => $this->contractors->created,
                'updated' => $this->contractors->updated,
                'pushed' => $this->contractors->pushed,
                'unchanged' => $this->contractors->unchanged,
                'skipped' => array_slice($this->contractors->skipped, 0, self::MAX_SKIPPED),
            ],
            'invoices' => [
                'created' => $this->invoices->created,
                'updated' => $this->invoices->updated,
                'unchanged' => $this->invoices->unchanged,
                'contractors_created' => $this->invoices->contractorsCreated,
                'skipped' => array_slice($this->invoices->skipped, 0, self::MAX_SKIPPED),
            ],
        ];
    }

    /**
     * Raport zapisany w koncie albo null, gdy go nie ma lub jest nieczytelny.
     *
     * @param array<array-key, mixed>|null $data
     */
    public static function fromArray(?array $data): ?self
    {
        $trigger = InvoicingSyncTrigger::tryFrom(is_string($data['trigger'] ?? null) ? $data['trigger'] : '');
        $startedAt = self::moment($data['started_at'] ?? null);

        if ($data === null || $trigger === null || $startedAt === null) {
            return null;
        }

        $report = new self($trigger, $startedAt);
        $report->finishedAt = self::moment($data['finished_at'] ?? null);
        $report->error = is_string($data['error'] ?? null) ? $data['error'] : null;
        $report->invoicesQueued = self::count($data, 'invoices_queued');
        $contractors = is_array($data['contractors'] ?? null) ? $data['contractors'] : [];
        $invoices = is_array($data['invoices'] ?? null) ? $data['invoices'] : [];
        $report->contractors->created = self::count($contractors, 'created');
        $report->contractors->updated = self::count($contractors, 'updated');
        $report->contractors->pushed = self::count($contractors, 'pushed');
        $report->contractors->unchanged = self::count($contractors, 'unchanged');
        $report->contractors->skipped = self::skipped($contractors);
        $report->invoices->created = self::count($invoices, 'created');
        $report->invoices->updated = self::count($invoices, 'updated');
        $report->invoices->unchanged = self::count($invoices, 'unchanged');
        $report->invoices->contractorsCreated = self::count($invoices, 'contractors_created');
        $report->invoices->skipped = self::skipped($invoices);

        return $report;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function count(array $data, string $key): int
    {
        $value = $data[$key] ?? 0;

        return is_int($value) ? $value : 0;
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return list<array{name: string, reason: string}>
     */
    private static function skipped(array $data): array
    {
        $entries = [];

        foreach (is_array($data['skipped'] ?? null) ? $data['skipped'] : [] as $entry) {
            if (is_array($entry) && is_string($entry['name'] ?? null) && is_string($entry['reason'] ?? null)) {
                $entries[] = ['name' => $entry['name'], 'reason' => $entry['reason']];
            }
        }

        return $entries;
    }

    private static function moment(mixed $value): ?CarbonImmutable
    {
        try {
            return is_string($value) && $value !== '' ? CarbonImmutable::parse($value) : null;
        } catch (Throwable) {
            return null;
        }
    }
}
