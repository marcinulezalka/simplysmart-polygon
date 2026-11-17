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

namespace Simplysmart\Polygon\EventLog\Sources;

use Carbon\CarbonImmutable;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use Simplysmart\Polygon\Contracts\EventLogSource;
use Simplysmart\Polygon\Database\History;
use Simplysmart\Polygon\Enums\EventChannel;
use Simplysmart\Polygon\EventLog\LoggedEvent;
use Simplysmart\Polygon\Models\ZoneAccount;
use Simplysmart\Polygon\Zones\ZoneDefinition;
use Simplysmart\Polygon\Zones\ZoneTable;

/**
 * Wpisy dziennika z tabel historii (`<tabela>_history`, docs/baza-danych.md §4) — wszystkich w bazie, więc tabela
 * każdej paczki utworzona przez History::create() trafia do dziennika sama: zmiany, których aktorem było konto (strefa
 * i id aktora), oraz zmiany rekordu samego konta (`<strefa>_accounts`) — jego utworzenie i zmiany robione przez
 * zalogowanego aktora (bez zapisów technicznych żądania logowania, które opisuje kanał bezpieczeństwa). Jedno zapytanie
 * UNION ALL po indeksie aktora (`<tabela>_history_actor_index`). Opis: `event_log.history.<akcja>` z nazwą tabeli
 * `event_log.tables.<tabela>` (teksty strefy, potem polygon; bez tekstu — nazwa tabeli).
 */
final class HistoryEvents implements EventLogSource
{
    /** Klucz nazwy tabeli kont strefy w `event_log.tables` (każda strefa ma własną tabelę `<strefa>_accounts`). */
    private const string ACCOUNTS_KEY = 'zone_accounts';

    /** @var list<string>|null */
    private ?array $tables = null;

    public function __construct(
        private readonly DatabaseManager $connections,
    ) {}

    public function recent(ZoneDefinition $zone, ZoneAccount $account, int $limit, ?EventChannel $channel = null): array
    {
        $accountId = $account->getKey();

        if (($channel !== null && $channel !== EventChannel::History) || ! is_int($accountId)) {
            return [];
        }

        $accounts = History::tableFor(ZoneTable::Accounts->for($zone->name));
        $union = null;

        foreach ($this->tables() as $table) {
            $query = $this->connections->connection()->table($table);
            $query->selectRaw('? as subject', [substr($table, 0, -strlen(History::TABLE_SUFFIX))]);
            $query->addSelect(['id', 'row_id', 'action', 'created_at']);
            $query->where(static function (Builder $where) use ($zone, $accountId, $table, $accounts): void {
                $where->where('actor_zone', $zone->name)->where('actor_id', $accountId);

                if ($table === $accounts) {
                    $where->orWhere(static fn(Builder $own): Builder => $own->where('row_id', $accountId)->where(static fn(Builder $by): Builder => $by->where('action', 'insert')->orWhereNotNull('actor_id')));
                }
            });
            $union === null ? $union = $query : $union->unionAll($query);
        }

        if ($union === null) {
            return [];
        }

        $rows = $this->connections->connection()->query()->fromSub($union, 'events')->orderByDesc('created_at')->orderByDesc('id')->limit($limit)->get();
        $events = [];

        foreach ($rows as $row) {
            $subject = is_string($row->subject) ? $row->subject : '';
            $action = is_string($row->action) ? $row->action : '';

            $events[] = new LoggedEvent(
                EventChannel::History,
                $action,
                text($zone->textKey('event_log.history.' . $action), ['subject' => $this->subjectLabel($zone, $subject)]),
                CarbonImmutable::parse(is_string($row->created_at) ? $row->created_at : 'now'),
                subject: $subject,
                subjectId: is_numeric($row->row_id) ? (int) $row->row_id : null,
            );
        }

        return $events;
    }

    private function subjectLabel(ZoneDefinition $zone, string $table): string
    {
        $key = 'event_log.tables.' . ($table === ZoneTable::Accounts->for($zone->name) ? self::ACCOUNTS_KEY : $table);
        $label = text($zone->textKey($key));

        return $label !== $key && $label !== $zone->textKey($key) ? $label : str_replace('_', ' ', $table);
    }

    /**
     * Tabele historii w bazie (raz na instancję).
     *
     * @return list<string>
     */
    private function tables(): array
    {
        return $this->tables ??= array_values(array_filter(
            $this->connections->connection()->getSchemaBuilder()->getTableListing(schemaQualified: false),
            static fn(string $table): bool => str_ends_with($table, History::TABLE_SUFFIX),
        ));
    }
}
