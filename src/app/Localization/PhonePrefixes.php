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

namespace Simplysmart\Polygon\Localization;

use Illuminate\Http\Request;
use Simplysmart\Polygon\Database\ColumnNames;
use Simplysmart\Polygon\Http\RequestInput;
use Simplysmart\Polygon\Models\Dictionaries\Country;
use Simplysmart\Polygon\Queries\Dictionaries\DefaultEntries;

/**
 * Numery kierunkowe pól telefonu (komponent pola telefonu strefy — każde pole typu telefon i komórka): aktywne kraje
 * słownika polygonu z numerem kierunkowym (kolejność słownika — kraj aplikacji i kraje wiodące na początku; lista
 * liczona raz na żądanie — usługa `scoped`), flaga jako emoji z kodu ISO alpha-2 (bez plików graficznych; system bez
 * emoji flag pokazuje dwie litery kodu), kraj domyślny (DefaultEntries), pole kraju obok pola numeru
 * (`<pole>_country` — countryField(), rules()), numer z kierunkowym do zapisu (join(), fromRequest()) i zapisany numer
 * rozdzielony na kraj i numer do pokazania w polu (split()).
 */
final readonly class PhonePrefixes
{
    /** Pole formularza z krajem numeru kierunkowego (kod ISO alpha-2). */
    public const string FIELD = 'phone_country';

    /** Najdłuższy numer w polu formularza. */
    public const int PHONE_MAX = 32;

    /** Przesunięcie z wielkiej litery A–Z do symbolu regionalnego (U+1F1E6 − 'A'). */
    private const int REGIONAL_INDICATOR_OFFSET = 0x1F1E6 - 0x41;

    public function __construct(
        private DefaultEntries $defaults,
    ) {}

    /**
     * Pole formularza z krajem kierunkowego dla pola numeru $field (`phone` → `phone_country` — FIELD).
     */
    public static function countryField(string $field): string
    {
        return $field . '_country';
    }

    /**
     * Reguły walidacji pola numeru $field (opcjonalny, do PHONE_MAX znaków) i pola kraju jego kierunkowego.
     *
     * @return array<string, list<string>>
     */
    public static function rules(string $field = 'phone'): array
    {
        return [
            $field => ['nullable', 'string', 'max:' . self::PHONE_MAX],
            self::countryField($field) => ['nullable', 'string', 'size:2'],
        ];
    }

    /**
     * Numer z formularza z kierunkowym wybranego kraju (pola `$field` i `<$field>_country`) — do zapisu.
     */
    public function fromRequest(Request $request, string $field = 'phone'): ?string
    {
        return $this->join(RequestInput::text($request, self::countryField($field)), RequestInput::text($request, $field));
    }

    /**
     * Zapisany numer rozdzielony do pola: kraj, którego kierunkowy zaczyna numer (najdłuższe dopasowanie; przy wspólnym
     * kierunkowym — kraj domyślny, potem pierwszy w kolejności słownika), i reszta numeru; bez kierunkowego — kraj null.
     *
     * @return array{country: ?string, number: string}
     */
    public function split(?string $phone): array
    {
        $phone = trim((string) $phone);

        if (! str_starts_with($phone, '+')) {
            return ['country' => null, 'number' => $phone];
        }

        $compact = str_replace(' ', '', $phone);
        $default = $this->defaultCountry();
        $match = null;

        foreach ($this->options() as $option) {
            if (! str_starts_with($compact, $option['dial'])) {
                continue;
            }

            $longer = $match === null || strlen($option['dial']) > strlen($match['dial']);
            $preferred = $match !== null && strlen($option['dial']) === strlen($match['dial']) && $option['code'] === $default;

            if ($longer || $preferred) {
                $match = $option;
            }
        }

        if ($match === null) {
            return ['country' => null, 'number' => $phone];
        }

        $digits = strlen($match['dial']) - 1;
        $rest = preg_replace('/^\+(?:\s*\d){' . $digits . '}/', '', $phone);

        return ['country' => $match['code'], 'number' => trim(is_string($rest) ? $rest : $phone)];
    }

    /**
     * Kraj z listy po kodzie; null bez kodu albo spoza listy.
     *
     * @return array{code: string, dial: string, flag: string, name: string}|null
     */
    public function option(?string $code): ?array
    {
        if ($code === null) {
            return null;
        }

        foreach ($this->options() as $option) {
            if ($option['code'] === strtoupper($code)) {
                return $option;
            }
        }

        return null;
    }

    /**
     * Kraje z numerem kierunkowym (raz na żądanie).
     *
     * @return list<array{code: string, dial: string, flag: string, name: string}>
     */
    public function options(): array
    {
        return once(fn(): array => $this->load());
    }

    /**
     * @return list<array{code: string, dial: string, flag: string, name: string}>
     */
    private function load(): array
    {
        $query = Country::query();
        $query->where(ColumnNames::IS_ACTIVE, true);
        $query->whereNotNull('dial_code');
        $query->orderBy(ColumnNames::SORT_ORDER);

        return array_values($query->get()->map(static fn(Country $country): array => [
            'code' => $country->alpha_code_2,
            'dial' => (string) $country->dial_code,
            'flag' => self::flag($country->alpha_code_2),
            'name' => $country->translatedName(),
        ])->all());
    }

    /**
     * Kod kraju zaznaczonego domyślnie (kraj aplikacji); null bez słownika.
     */
    public function defaultCountry(): ?string
    {
        return $this->defaults->country()?->alpha_code_2;
    }

    /**
     * Numer z kierunkowym kraju $country („+48 600 100 200”). Numer zaczynający się od „+” albo „00” zostaje bez zmian;
     * nieznany kraj albo kraj bez kierunkowego — sam numer.
     */
    public function join(?string $country, ?string $phone): ?string
    {
        if ($phone === null || $country === null || str_starts_with($phone, '+') || str_starts_with($phone, '00')) {
            return $phone;
        }

        $dial = Country::query()->where(Country::codeColumn(), strtoupper($country))->value('dial_code');

        return is_string($dial) && $dial !== '' ? $dial . ' ' . $phone : $phone;
    }

    /**
     * Flaga kraju jako emoji (dwa symbole regionalne z kodu alpha-2); kod spoza A–Z — pusty tekst.
     */
    public static function flag(string $code): string
    {
        $code = strtoupper($code);

        if (preg_match('/^[A-Z]{2}$/', $code) !== 1) {
            return '';
        }

        return mb_chr(ord($code[0]) + self::REGIONAL_INDICATOR_OFFSET, 'UTF-8') . mb_chr(ord($code[1]) + self::REGIONAL_INDICATOR_OFFSET, 'UTF-8');
    }
}
