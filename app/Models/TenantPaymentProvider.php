<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The TENANT's own PayChangu credentials — used to RECEIVE payments from
 * parents in the portal. Keys are encrypted at rest; money settles to the
 * tenant's linked bank account, never to the platform.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $provider
 * @property string|null $secret_key
 * @property string|null $public_key
 * @property string $mode
 * @property bool $is_active
 * @property Carbon|null $verified_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class TenantPaymentProvider extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'tenant_payment_providers';

    protected $fillable = [
        'tenant_id', 'provider', 'secret_key', 'public_key',
        'mode', 'is_active', 'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'secret_key' => 'encrypted',
            'public_key' => 'encrypted',
            'is_active' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    public function decryptedSecretKey(): string
    {
        return (string) $this->secret_key;
    }

    public function decryptedPublicKey(): string
    {
        return (string) $this->public_key;
    }
}
