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

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Throwable;

/**
 * Klient API Fakturowni jednego konta (`https://<subdomena>.fakturownia.pl`, token w parametrze `api_token`):
 * konto (account() — sprawdzenie danych dostępowych), klienci (clients() stronami po PER_PAGE, clientByExternalId(),
 * clientByTaxNo(), createClient(), updateClient()), faktury (createInvoice(), invoiceByOid() — wyszukanie po naszym
 * identyfikatorze, invoicePdf()). Ponawiane (RETRY_DELAYS): odczyty przy limicie żądań (429), błędzie serwera i braku
 * połączenia, zapisy tylko przy limicie żądań — po błędzie serwera albo zerwanym połączeniu nie wiadomo, czy zapis
 * doszedł, więc decyduje wywołujący (faktury chroni `oid`). Każda inna odmowa to FakturowniaFailed. Tworzony przez FakturowniaClients — token nie trafia do logów ani wyjątków.
 */
final readonly class FakturowniaClient
{
    /** Największa strona listy w API. */
    public const int PER_PAGE = 100;

    /** Limit czasu żądania w sekundach. */
    private const int TIMEOUT = 20;

    /** Odstępy ponowień w milisekundach (liczba ponowień = liczba odstępów). */
    private const array RETRY_DELAYS = [300, 1200];

    public function __construct(
        private Http $http,
        private string $subdomain,
        private string $token,
    ) {}

    /**
     * Dane konta — udana odpowiedź potwierdza subdomenę i token.
     *
     * @return array<array-key, mixed>
     */
    public function account(): array
    {
        return $this->object($this->get('account.json'));
    }

    /**
     * Strona klientów (od 1), najwyżej PER_PAGE; pusta lista — koniec.
     *
     * @return list<array<array-key, mixed>>
     */
    public function clients(int $page): array
    {
        return $this->list($this->get('clients.json', ['page' => $page, 'per_page' => self::PER_PAGE]));
    }

    /**
     * @return array<array-key, mixed>|null
     */
    public function clientByExternalId(string $externalId): ?array
    {
        return $this->list($this->get('clients.json', ['external_id' => $externalId]))[0] ?? null;
    }

    /**
     * @return array<array-key, mixed>|null
     */
    public function clientByTaxNo(string $taxNo): ?array
    {
        return $this->list($this->get('clients.json', ['tax_no' => $taxNo]))[0] ?? null;
    }

    /**
     * @param array<string, mixed> $client
     *
     * @return array<array-key, mixed>
     */
    public function createClient(array $client): array
    {
        return $this->object($this->send('post', 'clients.json', ['client' => $client]));
    }

    /**
     * @param array<string, mixed> $client
     *
     * @return array<array-key, mixed>
     */
    public function updateClient(string $id, array $client): array
    {
        return $this->object($this->send('put', 'clients/' . rawurlencode($id) . '.json', ['client' => $client]));
    }

    /**
     * @param array<string, mixed> $invoice
     *
     * @return array<array-key, mixed>
     */
    public function createInvoice(array $invoice): array
    {
        return $this->object($this->send('post', 'invoices.json', ['invoice' => $invoice]));
    }

    /**
     * Faktura o naszym identyfikatorze `oid` (token faktury) albo null — sprawdzenie przed wysyłką, czy nie ma duplikatu.
     *
     * @return array<array-key, mixed>|null
     */
    public function invoiceByOid(string $oid): ?array
    {
        foreach ($this->list($this->get('invoices.json', ['oid' => $oid])) as $invoice) {
            if (($invoice['oid'] ?? null) === $oid) {
                return $invoice;
            }
        }

        return null;
    }

    /**
     * Treść PDF faktury o id z Fakturowni.
     */
    public function invoicePdf(string $id): string
    {
        return $this->get('invoices/' . rawurlencode($id) . '.pdf')->body();
    }

    /**
     * @param array<string, scalar> $query
     */
    private function get(string $path, array $query = []): Response
    {
        return $this->respond(fn(): Response => $this->request(true)->get($path, ['api_token' => $this->token, ...$query]));
    }

    /**
     * @param 'post'|'put' $method
     * @param array<string, mixed> $body
     */
    private function send(string $method, string $path, array $body): Response
    {
        return $this->respond(fn(): Response => $this->request(false)->{$method}($path, ['api_token' => $this->token, ...$body]));
    }

    /**
     * @param bool $safe Odczyt — wolno go powtórzyć także po błędzie serwera i zerwanym połączeniu.
     */
    private function request(bool $safe): PendingRequest
    {
        return $this->http
            ->baseUrl('https://' . $this->subdomain . '.fakturownia.pl')
            ->acceptJson()
            ->asJson()
            ->timeout(self::TIMEOUT)
            ->retry(self::RETRY_DELAYS, when: static fn(Throwable $exception): bool => match (true) {
                $exception instanceof RequestException => $exception->response->status() === 429 || ($safe && $exception->response->serverError()),
                $exception instanceof ConnectionException => $safe,
                default => false,
            }, throw: false);
    }

    /**
     * @param callable(): Response $call
     *
     * @throws FakturowniaFailed
     */
    private function respond(callable $call): Response
    {
        try {
            $response = $call();
        } catch (ConnectionException) {
            throw FakturowniaFailed::unreachable('Brak połączenia z Fakturownią.');
        }

        return $response->successful() ? $response : throw FakturowniaFailed::fromResponse($response)->redacting($this->token);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function object(Response $response): array
    {
        $data = $response->json();

        return is_array($data) ? $data : [];
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    private function list(Response $response): array
    {
        return array_values(array_filter($this->object($response), is_array(...)));
    }
}
