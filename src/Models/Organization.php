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

namespace Simplysmart\Polygon\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Simplysmart\Polygon\Database\ColumnNames;
use Simplysmart\Polygon\Models\Dictionaries\Country;
use Simplysmart\Polygon\Models\Relations\BelongsToParentOrganization;
use Simplysmart\Polygon\Models\Relations\BelongsToUnitType;
use Simplysmart\Polygon\Models\Relations\HasOrganizationMembers;
use Simplysmart\Polygon\Models\Relations\HasResources;
use Simplysmart\Polygon\Models\Relations\LocatedInCountry;

/**
 * Organizacja: firma korzystająca z aplikacji (drzewo przez `parent_id`, np. firma → oddział), wspólna dla stref
 * (docs/baza-danych.md §16). Organizacja hero (HERO_ID, „Simplysmart”, HeroOrganizationSeeder) to organizacja
 * operatora aplikacji: jej członek pracuje jak hero — w kontekście każdej aktywnej organizacji — a frontend stref jej nie
 * pokazuje (scope visible(), Contracts\CurrentOrganization::contexts()).
 * Dane firmy są opcjonalne: nazwa skrócona (shortName()), NIP (10 cyfr; oddział może mieć NIP firmy), REGON, KRS,
 * adres z krajem (`country_code`, kod alpha-2 słownika krajów, relacja country()), telefon stacjonarny i komórkowy
 * (z prefiksem kraju, np. `+48 22 100 20 30`), e-mail i e-mail do rozliczeń oraz kolor (`color`, `#rrggbb`) do grafik
 * i zestawień — bez zapisanego color() daje stały kolor z palety COLOR_PALETTE według id.
 *
 * Departament (`is_department`, isDepartment()) to wewnętrzna agregacja: dział jednostki nadrzędnej (organizacji bez
 * flagi), z własnymi członkami, ale bez własnych danych. Departament nie ma pod sobą innych organizacji.
 *
 * @property bool $is_department
 * @property-read Organization|null $parent
 * @property ?string $short_name
 * @property ?string $color
 * @property ?string $nip
 * @property ?string $regon
 * @property ?string $krs
 * @property ?string $street
 * @property ?string $postal_code
 * @property ?string $city
 * @property ?string $country_code
 * @property-read Country|null $country
 * @property ?string $phone
 * @property ?string $mobile_phone
 * @property ?string $email
 * @property ?string $billing_email
 * @property ?int $unit_type_id
 * @property-read OrganizationUnitType|null $unitType
 */
final class Organization extends DataModel
{
    use BelongsToParentOrganization;
    use BelongsToUnitType;
    use HasOrganizationMembers;
    use HasResources;
    use LocatedInCountry;

    public const string TABLE = 'organizations';

    /** Klucz obcy organizacji w tabelach z nią powiązanych (np. OrganizationMember). */
    public const string FOREIGN_KEY = 'organization_id';

    /** Stałe id organizacji hero (operatora aplikacji) z puli seederów (IdSlots, 1–999). */
    public const int HERO_ID = 1;

    /**
     * Zarezerwowany klucz organizacji hero w plikach instalacji: wolno go użyć tylko jako `parent` (organizacje klientów
     * pod niewidoczną organizacją operatora); nie można go zdefiniować ani przypisać kontu.
     */
    public const string HERO_KEY = 'simplysmart';

    /** Nazwa organizacji hero przy jej założeniu (później może ją zmienić administrator). */
    public const string HERO_NAME = 'Simplysmart';

    public const string SHORT_NAME = 'short_name';

    public const string IS_DEPARTMENT = 'is_department';

    /** Typ jednostki ze słownika OrganizationUnitType (np. poradnia, filia, dział administracyjny); null — bez typu. */
    public const string UNIT_TYPE_ID = 'unit_type_id';

    /** Najdłuższa nazwa skrócona (kolumna `short_name`). */
    public const int SHORT_NAME_MAX_LENGTH = 100;

    public const string NIP = 'nip';

    public const string REGON = 'regon';

    public const string KRS = 'krs';

    public const string STREET = 'street';

    public const string POSTAL_CODE = 'postal_code';

    public const string CITY = 'city';

    public const string COUNTRY_CODE = 'country_code';

    public const string PHONE = 'phone';

    public const string MOBILE_PHONE = 'mobile_phone';

    /** Klucz `json_attributes` z ikoną organizacji (icon()). */
    public const string ICON_ATTRIBUTE = 'icon';

    /**
     * Ikony do wyboru dla organizacji (nazwy ikon, które rysuje paczka strefy); pierwsza jest domyślna.
     *
     * @var list<string>
     */
    public const array ICONS = ['building', 'users', 'cross', 'heart', 'activity', 'invoice', 'settings', 'clipboard', 'cpu', 'shield-check', 'book-open', 'home', 'grid'];

    /** Kolor organizacji w grafikach i zestawieniach (`#rrggbb`, color()). */
    public const string COLOR = 'color';

    /**
     * Kolory domyślne organizacji bez zapisanego koloru (wybór według id, color()): dobrze rozróżnialne odcienie.
     *
     * @var list<string>
     */
    public const array COLOR_PALETTE = ['#2563eb', '#16a34a', '#dc2626', '#9333ea', '#ea580c', '#0891b2', '#ca8a04', '#db2777', '#4f46e5', '#0d9488'];

    public const string EMAIL = 'email';

    public const string BILLING_EMAIL = 'billing_email';

    /**
     * Kolumny danych firmy (bez nazwy i nazwy skróconej) z najwyższą długością, w kolejności formularza.
     *
     * @var array<string, int>
     */
    public const array DETAIL_LENGTHS = [
        self::NIP => 10,
        self::REGON => 14,
        self::KRS => 10,
        self::PHONE => 32,
        self::MOBILE_PHONE => 32,
        self::EMAIL => 255,
        self::BILLING_EMAIL => 255,
        self::STREET => 255,
        self::POSTAL_CODE => 10,
        self::CITY => 100,
        self::COUNTRY_CODE => 2,
        self::COLOR => 7,
    ];

    /**
     * Kolumny telefonów (zapisywane z prefiksem kraju).
     *
     * @var list<string>
     */
    public const array PHONES = [self::PHONE, self::MOBILE_PHONE];

    protected $table = self::TABLE;

    /**
     * Wartości domyślne kolumn jak w migracji.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [self::IS_DEPARTMENT => false];

    /**
     * Czy to organizacja hero (operatora aplikacji, HERO_ID).
     */
    public function isHero(): bool
    {
        return $this->getKey() === self::HERO_ID;
    }

    public function isDepartment(): bool
    {
        return $this->getAttribute(self::IS_DEPARTMENT) === true;
    }

    /**
     * Ikona organizacji z ICONS zapisana w `json_attributes.icon`, a bez niej (albo spoza listy) $fallback.
     */
    public function icon(string $fallback = self::ICONS[0]): string
    {
        $attributes = $this->getAttribute(ColumnNames::JSON_ATTRIBUTES);
        $icon = is_array($attributes) ? ($attributes[self::ICON_ATTRIBUTE] ?? null) : null;

        return is_string($icon) && in_array($icon, self::ICONS, true) ? $icon : $fallback;
    }

    /**
     * Kolor organizacji (`#rrggbb`): zapisany, a bez niego stały kolor z COLOR_PALETTE według id.
     */
    public function color(): string
    {
        $color = $this->getAttribute(self::COLOR);
        $id = $this->getKey();

        return is_string($color) && $color !== '' ? $color : self::COLOR_PALETTE[(is_int($id) ? $id : 0) % count(self::COLOR_PALETTE)];
    }

    /**
     * Nazwa skrócona, a bez niej pełna nazwa.
     */
    public function shortName(): string
    {
        return $this->short_name ?? $this->name;
    }

    /**
     * Nazwa w przełączniku kontekstu: departament z nazwą skróconą jednostki („Jednostka · Departament”), bo nazwy
     * departamentów powtarzają się między jednostkami; `$short` bierze nazwy skrócone.
     */
    public function contextName(bool $short = false): string
    {
        $name = $short ? $this->shortName() : $this->name;

        return $this->isDepartment() && $this->parent !== null ? $this->parent->shortName() . ' · ' . $name : $name;
    }

    /**
     * Adres w jednej linii (pomija puste części).
     */
    public function address(): string
    {
        return implode(', ', array_filter([$this->street, trim(($this->postal_code ?? '') . ' ' . ($this->city ?? ''))]));
    }

    /**
     * Organizacje widoczne we frontendzie stref: bez organizacji hero.
     *
     * @param Builder<static> $query
     */
    #[Scope]
    protected function visible(Builder $query): void
    {
        $query->whereKeyNot(self::HERO_ID);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [self::SHORT_NAME => 'string', self::IS_DEPARTMENT => 'boolean', self::UNIT_TYPE_ID => 'integer', ...array_fill_keys(array_keys(self::DETAIL_LENGTHS), 'string')];
    }
}
