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

namespace Simplysmart\Polygon\Integrations\Fakturownia;

use Illuminate\Http\Client\Response;
use RuntimeException;

/**
 * Fakturownia odrzuciła żądanie albo nie odpowiedziała: `$status` to kod HTTP (0 — brak połączenia), komunikat to
 * treść błędu z API („pole: komunikat” dla błędów walidacji 422) bez danych dostępowych (redacting() — token zastąpiony
 * gwiazdkami). unauthorized() — zły token
 * albo subdomena (401, 403, 404 konta), retryable() — warto ponowić później (limit żądań, błąd serwera, brak połączenia).
 */
final class FakturowniaFailed extends RuntimeException
{
    /** Kod zastępczy, gdy nie doszło do odpowiedzi HTTP. */
    public const int NO_RESPONSE = 0;

    public function __construct(
        public readonly int $status,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function fromResponse(Response $response): self
    {
        return new self($response->status(), self::describe($response));
    }

    /**
     * Ten sam błąd bez sekretu w komunikacie — Fakturownia potrafi zwrócić w treści adres żądania z `api_token`.
     */
    public function redacting(string $secret): self
    {
        return $secret === '' || ! str_contains($this->getMessage(), $secret) ? $this : new self($this->status, str_replace([$secret, rawurlencode($secret)], '***', $this->getMessage()));
    }

    public static function unreachable(string $reason): self
    {
        return new self(self::NO_RESPONSE, $reason);
    }

    public function unauthorized(): bool
    {
        return in_array($this->status, [401, 403], true);
    }

    public function retryable(): bool
    {
        return $this->status === self::NO_RESPONSE || $this->status === 429 || $this->status >= 500;
    }

    /**
     * Treść błędu z odpowiedzi: `message` jako tekst albo mapa „pole → komunikaty” (422), inaczej sam kod HTTP.
     */
    private static function describe(Response $response): string
    {
        $message = $response->json('message') ?? $response->json('error');

        if (is_string($message) && $message !== '') {
            return $message;
        }

        if (is_array($message)) {
            $parts = [];

            foreach ($message as $field => $errors) {
                $text = implode(', ', array_filter(is_array($errors) ? $errors : [$errors], is_string(...)));
                $parts[] = is_string($field) ? $field . ': ' . $text : $text;
            }

            return implode('; ', array_filter($parts, static fn(string $part): bool => trim($part, ': ') !== ''));
        }

        return 'HTTP ' . $response->status();
    }
}
