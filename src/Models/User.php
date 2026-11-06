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

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Simplysmart\Polygon\Database\Factories\UserFactory;
use Simplysmart\Polygon\Models\Concerns\HasChecksum;
use Simplysmart\Polygon\Models\Concerns\HasRouteToken;
use Simplysmart\Polygon\Models\Concerns\HasStandardColumns;
use Spatie\Permission\Traits\HasRoles;

/**
 * Użytkownik aplikacji na tabeli `users` (stockowa migracja + kolumny standardowe z `add_standard_columns_to_users_table`).
 * Zastępuje `App\Models\User`: PolygonServiceProvider ustawia go jako model providera `users`, gdy skonfigurowanego
 * modelu nie ma. Standard DataModel (token, casty, checksum, soft delete) dają traity paczki, role i uprawnienia —
 * spatie (`HasRoles`).
 *
 * @property int $id
 * @property string|null $token
 * @property int|null $parent_id
 * @property string $name
 * @property string|null $status
 * @property bool $is_active
 * @property bool $is_default
 * @property int $sort_order
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property array<array-key, mixed>|null $json_attributes
 * @property array<array-key, mixed>|null $json_default_settings
 * @property list<string>|null $json_tags
 * @property array<array-key, mixed>|null $json_migrations
 * @property string|null $checksum
 * @property array<array-key, mixed>|null $json_archive
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
final class User extends Authenticatable
{
    use HasChecksum;

    /** @use HasFactory<UserFactory> */
    use HasFactory;
    use HasRoles;
    use HasRouteToken;
    use HasStandardColumns;
    use Notifiable;
    use SoftDeletes;

    public const string TABLE = 'users';

    protected $table = self::TABLE;

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email' => 'string',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'remember_token' => 'string',
        ];
    }
}
