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

namespace Simplysmart\Polygon\Tests\Integration\EventLog;

use Illuminate\Support\Facades\DB;
use Simplysmart\Polygon\Auth\Events\SignInSucceeded;
use Simplysmart\Polygon\Contracts\ZoneRegistry;
use Simplysmart\Polygon\Database\History;
use Simplysmart\Polygon\Enums\EventChannel;
use Simplysmart\Polygon\EventLog\LoggedEvent;
use Simplysmart\Polygon\Models\Organization;
use Simplysmart\Polygon\Models\ZoneAccount;
use Simplysmart\Polygon\Organizations\OrganizationMembership;
use Simplysmart\Polygon\Queries\EventLog\AccountEventLog;
use Simplysmart\Polygon\Tests\Fixtures\EventLog\ApiCalls;
use Simplysmart\Polygon\Tests\Fixtures\Zones\AlphaAccount;
use Simplysmart\Polygon\Tests\Integration\Zones\ZoneTestCase;

/**
 * Dziennik zdarzeń konta (Queries\EventLog\AccountEventLog) w każdej strefie bez konfiguracji: zdarzenia bezpieczeństwa
 * z Auth\Events, powiadomienia z komunikatu statusu żądania, zmiany z tabel historii po aktorze i rekordzie konta, filtr
 * kanału oraz źródło dołożone przez paczkę (tag SOURCES_TAG).
 */
class AccountEventLogTest extends ZoneTestCase
{
    public function test_security_events_and_notifications_are_logged_for_the_account(): void
    {
        $account = $this->account(AlphaAccount::class);
        $south = $this->organization('Południe');
        $membership = $this->app->make(OrganizationMembership::class);
        $membership->add($account, $this->organization('Północ'));
        $membership->add($account, $south);

        event(new SignInSucceeded('alpha', $account, '10.0.0.7'));
        $this->signedIn($account, 'alpha')->post('/alpha/account/organization', ['organization' => $south->token])->assertRedirect();

        $notifications = $this->log($account, EventChannel::Notification);
        $this->assertSame([text('account.organization.switched', ['name' => 'Południe'])], array_map(static fn(LoggedEvent $event): string => $event->label, $notifications));

        $security = $this->log($account, EventChannel::Security);
        $this->assertSame(['sign_in'], array_map(static fn(LoggedEvent $event): string => $event->type, $security));
        $this->assertSame(text('event_log.types.sign_in'), $security[0]->label);
        $this->assertSame('10.0.0.7', $security[0]->ip);
    }

    public function test_history_lists_changes_made_by_the_account_and_changes_of_its_own_record(): void
    {
        $account = $this->account(AlphaAccount::class);
        $other = $this->account(AlphaAccount::class, ['email' => 'ola@example.com']);
        $organization = $this->organization('Północ');
        $this->history(Organization::TABLE, $organization->id, 'update', $account, '2026-11-19 10:00:00');
        $this->history(Organization::TABLE, $organization->id, 'delete', $other, '2026-11-19 11:00:00');

        $history = $this->log($account, EventChannel::History);

        $this->assertSame([
            [text('event_log.history.update', ['subject' => text('event_log.tables.organizations')]), Organization::TABLE, $organization->id],
            [text('event_log.history.insert', ['subject' => text('event_log.tables.zone_accounts')]), 'alpha_accounts', $account->id],
        ], array_map(static fn(LoggedEvent $event): array => [$event->label, $event->subject, $event->subjectId], $history));
    }

    public function test_a_package_adds_its_own_source_and_every_channel_is_merged_newest_first(): void
    {
        $account = $this->account(AlphaAccount::class);
        $this->app->instance(ApiCalls::class, new ApiCalls());
        $this->app->tag([ApiCalls::class], AccountEventLog::SOURCES_TAG);
        event(new SignInSucceeded('alpha', $account, null));

        $channels = array_map(static fn(LoggedEvent $event): EventChannel => $event->channel, $this->log($account));

        $this->assertSame(EventChannel::Api, $channels[0]);
        $this->assertContains(EventChannel::Security, $channels);
        $this->assertContains(EventChannel::History, $channels);
        $this->assertSame([], $this->log($this->account(AlphaAccount::class, ['email' => 'ola@example.com']), EventChannel::Security));
    }

    /**
     * @return list<LoggedEvent>
     */
    private function log(ZoneAccount $account, ?EventChannel $channel = null): array
    {
        return $this->app->make(AccountEventLog::class)->recent($this->app->make(ZoneRegistry::class)->get('alpha'), $account, channel: $channel);
    }

    private function history(string $table, int $rowId, string $action, ZoneAccount $actor, string $at): void
    {
        DB::table(History::tableFor($table))->insert(['row_id' => $rowId, 'action' => $action, 'actor_zone' => 'alpha', 'actor_id' => $actor->id, 'created_at' => $at]);
    }
}
