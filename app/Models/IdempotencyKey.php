<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

/**
 * Reservation + cached response for an Idempotency-Key (see the
 * `EnsureIdempotency` middleware). Scoped by tenant bucket so identical
 * keys from different tenants never collide.
 */
final class IdempotencyKey extends Model
{
    use HasUuid;

    protected $fillable = [
        'scope',
        'key',
        'method',
        'path',
        'response_status',
        'response_body',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];
}
