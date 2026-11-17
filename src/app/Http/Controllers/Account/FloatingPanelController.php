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

namespace Simplysmart\Polygon\Http\Controllers\Account;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Simplysmart\Polygon\Contracts\CurrentAccount;
use Simplysmart\Polygon\Enums\FloatingPanelPosition;
use Simplysmart\Polygon\FloatingPanel\FloatingPanelPreference;
use Simplysmart\Polygon\Http\StatusMessage;

/**
 * Zapis położenia pływającego panelu akcji konta (formularz PUT z polem FIELD = FloatingPanelPosition, np. z ustawień
 * wyglądu strefy); wraca na poprzednią stronę z komunikatem `account.floating_panel.saved`.
 */
final readonly class FloatingPanelController
{
    public const string FIELD = 'position';

    public function __construct(
        private CurrentAccount $account,
        private FloatingPanelPreference $preference,
        private Redirector $redirect,
    ) {}

    /**
     * @throws ValidationException Gdy położenie jest nieznane.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([self::FIELD => ['required', 'string', Rule::in(FloatingPanelPosition::values())]]);
        $position = FloatingPanelPosition::from(is_string($validated[self::FIELD]) ? $validated[self::FIELD] : '');
        $this->preference->save($this->account->require(), $position);

        return $this->redirect->back()->with(StatusMessage::KEY, text('account.floating_panel.saved', ['position' => $position->label()]));
    }
}
