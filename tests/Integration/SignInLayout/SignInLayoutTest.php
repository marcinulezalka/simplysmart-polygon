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

namespace Simplysmart\Polygon\Tests\Integration\SignInLayout;

use Dom\HTMLDocument;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Simplysmart\Polygon\Http\StatusMessage;
use Tests\TestCase;

/**
 * Layout obszaru logowania (`polygon::layouts.sign-in`): jeden nagłówek h1, błędy przy polach albo nad formularzem i komunikat
 * po akcji.
 */
class SignInLayoutTest extends TestCase
{
    private const string SCREEN = <<<'BLADE'
        @extends('polygon::layouts.sign-in')
        @section('error-fields', 'email, code')
        @section('hero')<x-polygon::sign-in.hero lead="Hasło" />@endsection
        @section('panel')
            <form><x-polygon::sign-in.field id="email" name="email" label="E-mail" type="email" /><x-polygon::sign-in.submit>Dalej</x-polygon::sign-in.submit></form>
        @endsection
        BLADE;

    public function test_the_hero_is_the_heading_of_a_screen_without_a_title(): void
    {
        $screen = $this->screen(self::SCREEN);

        $this->assertSame(['Hasło'], $this->texts($screen, 'h1'));
        $this->assertSame($screen->querySelector('h1')?->id, $screen->querySelector('section')?->getAttribute('aria-labelledby'));
        $this->assertNotNull($screen->querySelector('form input#email[name="email"][type="email"]:not([aria-invalid])'));
        $this->assertNotNull($screen->querySelector('form button[type="submit"][data-pg-pinpad-submit]'));
    }

    public function test_the_title_is_the_heading_and_is_escaped_once(): void
    {
        $screen = $this->screen(self::SCREEN . "\n@section('title', 'Kod & PIN')\n@section('heading-hidden', '1')");

        $this->assertSame(['Kod & PIN'], $this->texts($screen, 'h1'));
        $this->assertStringStartsWith('Kod & PIN — ', $screen->title);
        $this->assertNotNull($screen->querySelector('h1.pg-sign-in__title--hidden'));
    }

    public function test_a_screen_without_a_title_and_a_hero_is_headed_by_the_brand(): void
    {
        $screen = $this->screen("@extends('polygon::layouts.sign-in')\n@section('brand', 'Znak')\n@section('panel', 'x')");

        $this->assertSame(['Znak'], $this->texts($screen, 'h1'));
    }

    public function test_errors_stay_at_their_fields_and_the_rest_above_the_form(): void
    {
        $errors = (new ViewErrorBag())->put('default', new MessageBag(['email' => 'Zły adres.', 'throttle' => 'Za dużo prób.']));
        $screen = $this->screen(self::SCREEN, ['errors' => $errors]);

        $this->assertSame(['Zły adres.'], $this->texts($screen, '#email-error'));
        $this->assertNotNull($screen->querySelector('input[aria-invalid="true"][aria-describedby="email-error"]'));
        $this->assertSame(['Za dużo prób.'], $this->texts($screen, '.pg-sign-in__message[role="alert"]'));
    }

    public function test_the_status_message_is_shown(): void
    {
        session()->flash(StatusMessage::KEY, 'Wysłano kod.');

        $this->assertSame(['Wysłano kod.'], $this->texts($this->screen(self::SCREEN), '.pg-sign-in__message[role="status"]'));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function screen(string $blade, array $data = []): HTMLDocument
    {
        view()->share($data);

        return HTMLDocument::createFromString(Blade::render($blade), LIBXML_NOERROR);
    }

    /**
     * @return list<string>
     */
    private function texts(HTMLDocument $screen, string $selector): array
    {
        $texts = [];

        foreach ($screen->querySelectorAll($selector) as $element) {
            $texts[] = trim((string) $element->textContent);
        }

        return $texts;
    }
}
