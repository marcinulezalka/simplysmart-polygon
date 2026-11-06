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

namespace Simplysmart\Polygon\Mail;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\SentMessage;
use LogicException;
use Simplysmart\Polygon\Contracts\CurrentZone;
use Simplysmart\Polygon\Contracts\ZoneRegistry;
use Simplysmart\Polygon\Zones\ZoneDefinition;

/**
 * E-mail strefy: HTML i (opcjonalnie) wersja tekstowa z widoków paczki strefy, teksty z kaskady (paczka strefy →
 * polygon). Polygon nie ma widoków maili: strefa podaje prefiks `mail.views` (np. `shop::mail`), a mail używa
 * `<prefiks>.<szablon>` (HTML, wymagany) i `<prefiks>.text.<szablon>` (gdy istnieje). Widoki dostają `$mailZone`.
 *
 * Mail niesie nazwę strefy i język, a wysyłka i podgląd działają wewnątrz strefy (CurrentZone::within), więc wynik jest
 * ten sam w żądaniu i w workerze kolejki.
 */
abstract class ZoneMailable extends Mailable
{
    private const string ZONE_TEXT = '.text.';

    /**
     * @param string $zoneName Strefa, z której idzie mail (jej widoki i teksty).
     */
    public function __construct(
        public readonly string $zoneName,
    ) {
        $this->locale(Container::getInstance()->make(Translator::class)->getLocale());
    }

    /**
     * Nazwa szablonu (`login-code` → `<prefiks>.login-code`).
     */
    abstract protected function template(): string;

    /**
     * Klucz tematu w grupie e-maili, bez namespace (`login_code.subject`).
     */
    abstract protected function subjectKey(): string;

    public function envelope(): Envelope
    {
        return new Envelope(subject: text_email($this->zone()->textKey($this->subjectKey())));
    }

    /**
     * @throws LogicException Gdy strefa nie ma `mail.views` albo widoku HTML szablonu.
     */
    public function content(): Content
    {
        $zone = $this->zone();
        $prefix = $zone->mailViews ?? throw new LogicException("Zone [{$zone->name}] needs mail.views to send e-mails.");
        $html = "{$prefix}.{$this->template()}";
        $text = $prefix . self::ZONE_TEXT . $this->template();

        if (! $this->views()->exists($html)) {
            throw new LogicException("Zone [{$zone->name}] has no mail view [{$html}].");
        }

        return new Content(
            html: $html,
            text: $this->views()->exists($text) ? $text : null,
            with: ['mailZone' => $zone],
        );
    }

    /**
     * @param \Illuminate\Contracts\Mail\Factory|\Illuminate\Contracts\Mail\Mailer $mailer
     */
    public function send($mailer)
    {
        return $this->withinZone(fn(): ?SentMessage => parent::send($mailer));
    }

    public function render()
    {
        return $this->withinZone(fn(): string => parent::render());
    }

    protected function zone(): ZoneDefinition
    {
        return Container::getInstance()->make(ZoneRegistry::class)->get($this->zoneName);
    }

    /**
     * @template TResult
     *
     * @param Closure(): TResult $callback
     * @return TResult
     */
    private function withinZone(Closure $callback): mixed
    {
        return Container::getInstance()->make(CurrentZone::class)->within($this->zone(), static fn(ZoneDefinition $zone): mixed => $callback());
    }

    private function views(): ViewFactory
    {
        return Container::getInstance()->make(ViewFactory::class);
    }
}
