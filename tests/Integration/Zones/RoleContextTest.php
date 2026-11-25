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

namespace Simplysmart\Polygon\Tests\Integration\Zones;

use Simplysmart\Polygon\Contracts\ZoneRegistry;
use Simplysmart\Polygon\Database\ColumnNames;
use Simplysmart\Polygon\Http\StatusMessage;
use Simplysmart\Polygon\Models\Organization;
use Simplysmart\Polygon\Models\ZoneAccount;
use Simplysmart\Polygon\Organizations\DefaultOrganization;
use Simplysmart\Polygon\Organizations\LastContext;
use Simplysmart\Polygon\Organizations\OrganizationContext;
use Simplysmart\Polygon\Organizations\OrganizationMembership;
use Simplysmart\Polygon\Permissions\OrganizationRoles;
use Simplysmart\Polygon\Tests\Fixtures\Zones\AlphaAccount;
use Simplysmart\Polygon\Tests\Fixtures\Zones\BetaAccount;

/**
 * Kontekst organizacja + rola (strefa z `role_contexts` — fikstura beta): konto z kilkoma rolami w organizacji pracuje
 * w jednej naraz (uprawnienia tylko z niej), domyślnie w najszerszej (konteksty organizacji od najważniejszej roli),
 * przełącza się między parami w tej samej sesji, a zapisany domyślny kontekst (organizacja i rola) obowiązuje po
 * kolejnym logowaniu. Strefa bez flagi liczy sumę ról.
 */
class RoleContextTest extends ZoneTestCase
{
    public function test_account_works_in_one_role_at_a_time_and_switches_without_signing_in_again(): void
    {
        [$account, $clinic, $head] = $this->staff();

        $this->signedIn($account, 'beta')->get('/beta/context')->assertExactJson([
            'id' => $clinic->id,
            'role' => 'registration',
            'roles' => ['registration'],
            'contexts' => [
                ['Poradnia', 'registration', true, false],
                ['Poradnia', 'doctor', false, false],
                ['Zakład', 'owner', false, false],
                ['Zakład', 'manager', false, false],
            ],
        ]);
        $this->get('/beta/desk')->assertOk();
        $this->get('/beta/visits')->assertForbidden();

        $this->post('/beta/account/organization', ['organization' => $clinic->token, 'role' => 'doctor'])
            ->assertRedirect(route('beta.dashboard'))
            ->assertSessionHas(StatusMessage::KEY, text('account.organization.switched', ['name' => $this->label($clinic, 'doctor')]));
        $this->get('/beta/context')->assertJsonPath('role', 'doctor')->assertJsonPath('roles', ['doctor']);
        $this->get('/beta/visits')->assertOk();
        $this->get('/beta/desk')->assertForbidden();

        $this->post('/beta/account/organization', ['organization' => $head->token, 'role' => 'owner'])->assertRedirect(route('beta.dashboard'));
        $this->get('/beta/context')->assertJsonPath('id', $head->id)->assertJsonPath('role', 'owner');
        $this->get('/beta/desk')->assertOk();
    }

    public function test_account_cannot_switch_to_a_role_it_does_not_have_in_the_organization(): void
    {
        [$account, $clinic] = $this->staff();

        $this->signedIn($account, 'beta')->post('/beta/account/organization', ['organization' => $clinic->token, 'role' => 'owner'])->assertRedirect();
        $this->assertSessionError('organization', text('account.organization.unavailable'));
        $this->get('/beta/context')->assertJsonPath('id', $clinic->id)->assertJsonPath('role', 'registration');
    }

    public function test_saved_default_context_is_the_organization_and_role_after_the_next_sign_in(): void
    {
        [$account, $clinic, $head] = $this->staff();
        $account->forceFill([ColumnNames::JSON_DEFAULT_SETTINGS => ['report' => 'weekly']])->save();

        $this->signedIn($account, 'beta')->from('/beta/context')->post('/beta/account/organization/default', ['organization' => $head->token, 'role' => 'manager'])
            ->assertRedirect('/beta/context')
            ->assertSessionHas(StatusMessage::KEY, text('account.organization.default_saved', ['name' => $this->label($head, 'manager')]));
        $this->get('/beta/context')->assertJsonPath('id', $clinic->id)->assertJsonPath('contexts.3', ['Zakład', 'manager', false, true]);
        $this->assertSame(['report' => 'weekly', DefaultOrganization::SETTING => $head->id, DefaultOrganization::ROLE_SETTING => 'manager'], $account->refresh()->getAttribute(ColumnNames::JSON_DEFAULT_SETTINGS));

        $this->post(route('beta.auth.logout'));
        $this->signedIn($account->refresh(), 'beta')->get('/beta/context')->assertJsonPath('id', $head->id)->assertJsonPath('role', 'manager')->assertJsonPath('roles', ['manager']);
        $this->get('/beta/desk')->assertForbidden();
    }

    public function test_expired_session_returns_to_the_last_context_and_signing_out_to_the_default_one(): void
    {
        [$account, $clinic, $head] = $this->staff();
        $this->signedIn($account, 'beta')->post('/beta/account/organization', ['organization' => $head->token, 'role' => 'manager']);

        $this->app->make('session.store')->flush();
        $this->signedIn($account->refresh(), 'beta')->get('/beta/context')->assertJsonPath('id', $head->id)->assertJsonPath('role', 'manager');

        $this->post(route('beta.auth.logout'));
        $this->assertNull($account->refresh()->defaultSetting(LastContext::SETTING));
        $this->signedIn($account, 'beta')->get('/beta/context')->assertJsonPath('id', $clinic->id)->assertJsonPath('role', 'registration');
    }

    public function test_last_context_that_the_account_lost_falls_back_to_the_default_one(): void
    {
        [$account, $clinic, $head] = $this->staff();
        $this->signedIn($account, 'beta')->post('/beta/account/organization', ['organization' => $head->token, 'role' => 'manager']);
        $head->forceFill([ColumnNames::IS_ACTIVE => false])->save();

        $this->app->make('session.store')->flush();
        $this->signedIn($account->refresh(), 'beta')->get('/beta/context')->assertJsonPath('id', $clinic->id)->assertJsonPath('role', 'registration');
    }

    public function test_zone_without_role_contexts_counts_the_sum_of_roles(): void
    {
        $account = $this->account(AlphaAccount::class);
        $clinic = $this->organization('Poradnia');
        $this->app->make(OrganizationMembership::class)->add($account, $clinic);
        $this->role('doctor', 'alpha')->givePermissionTo($this->permission('desk.admit', 'alpha'));
        $this->role('analyst', 'alpha')->givePermissionTo($this->permission('reports.view', 'alpha'));
        $this->app->make(OrganizationRoles::class)->assign($account, ['analyst', 'doctor'], $clinic->id);

        $this->signedIn($account, 'alpha')->get('/alpha/reports')->assertOk();
        $this->get('/alpha/desk')->assertOk();
        $this->get('/alpha/organization')->assertJsonPath('contexts', [['Poradnia', true]]);
    }

    /**
     * Konto beta jak personel placówki: w poradni (najstarsze członkostwo) lekarz i rejestracja (najszersza), w zakładzie
     * właściciel i kierownik.
     *
     * @return array{ZoneAccount, Organization, Organization}
     */
    private function staff(): array
    {
        $account = $this->account(BetaAccount::class);
        $clinic = $this->organization('Poradnia');
        $head = $this->organization('Zakład');
        $membership = $this->app->make(OrganizationMembership::class);
        $roles = $this->app->make(OrganizationRoles::class);
        $desk = $this->permission('desk.manage', 'beta');
        $visits = $this->permission('visits.admit', 'beta');
        $this->role('doctor', 'beta')->givePermissionTo($visits);
        $this->role('registration', 'beta')->givePermissionTo($desk, $this->permission('desk.view', 'beta'));
        $this->role('owner', 'beta')->givePermissionTo($desk, $visits, $this->permission('reports.view', 'beta'));
        $this->role('manager', 'beta')->givePermissionTo($this->permission('schedule.plan', 'beta'));
        $membership->add($account, $clinic);
        $membership->add($account, $head);
        $roles->assign($account, ['doctor', 'registration'], $clinic->id);
        $roles->assign($account, ['manager', 'owner'], $head->id);

        return [$account, $clinic, $head];
    }

    private function label(Organization $organization, string $role): string
    {
        return (new OrganizationContext($organization, false, role: $role))->label($this->app->make(ZoneRegistry::class)->get('beta'));
    }
}
