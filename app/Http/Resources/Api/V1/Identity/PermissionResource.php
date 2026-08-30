<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Identity;

use App\Http\Resources\Api\V1\CapabilityResource;
use Illuminate\Http\Request;

/**
 * Permissions are catalog entries (config-backed), not Eloquent models.
 * The controller feeds this resource an associative array.
 */
final class PermissionResource extends CapabilityResource
{
    public function toArray(Request $request): array
    {
        /** @var array{key:string,domain:string,label:string,description:string} $r */
        $r = (array) $this->resource;

        return [
            'key' => $r['key'],
            'domain' => $r['domain'],
            'label' => $r['label'],
            'description' => $r['description'],
        ];
    }
}
