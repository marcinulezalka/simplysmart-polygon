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

namespace Simplysmart\Polygon\Zones;

use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Str;

/**
 * Hash sesji w adresie ekranów strefy po zalogowaniu (`url_key` strefy): `/<slug>/<hash>/…`. Losowy dla każdej sesji
 * strefy (nowy po wylogowaniu, gdy sesja jest unieważniana), więc link skopiowany przez robota albo z innej sesji nie
 * prowadzi do ekranu (404). Niezalogowany z takim linkiem trafia na logowanie (auth działa przed sprawdzeniem hasha),
 * a po zalogowaniu Auth\SignIn\CompleteSignIn podmienia w zamierzonej stronie hash na bieżący (rewrite()). Trasy dostają go z URL::defaults (Http\Middleware\ApplyZoneUrlKey), a sprawdza go
 * Http\Middleware\RequireZoneUrlKey.
 */
final readonly class ZoneUrlKey
{
    /** Parametr trasy z hashem. */
    public const string PARAMETER = 'zoneKey';

    /** Klucz w sesji strefy. */
    public const string SESSION_KEY = 'polygon.url_key';

    /** Długość hasha (małe litery i cyfry). */
    public const int LENGTH = 20;

    /** Wzorzec parametru trasy. */
    public const string PATTERN = '[a-z0-9]{' . self::LENGTH . '}';

    /**
     * Hash bieżącej sesji (tworzony przy pierwszym użyciu).
     */
    public function current(Session $session): string
    {
        $key = $session->get(self::SESSION_KEY);

        if (! is_string($key) || strlen($key) !== self::LENGTH) {
            $key = Str::lower(Str::random(self::LENGTH));
            $session->put(self::SESSION_KEY, $key);
        }

        return $key;
    }

    /**
     * Adres z hashem bieżącej sesji w miejscu hasha na początku ścieżki, po slugu strefy ($slug, '' — bez segmentu), np. zamierzona strona zapamiętana przed
     * zalogowaniem z hashem poprzedniej sesji (po wylogowaniu albo wygaśnięciu sesji); adres bez hasha zostaje bez zmian.
     */
    public function rewrite(string $url, string $slug, Session $session): string
    {
        $segment = $slug === '' ? '' : preg_quote($slug, '#') . '/';
        $pattern = '#^((?:[a-z][a-z0-9+.-]*://[^/]+)?/' . $segment . ')' . self::PATTERN . '(?=[/?\#]|$)#i';

        return (string) preg_replace($pattern, '${1}' . $this->current($session), $url, 1);
    }

    public function matches(Session $session, mixed $candidate): bool
    {
        return is_string($candidate) && hash_equals($this->current($session), $candidate);
    }
}
