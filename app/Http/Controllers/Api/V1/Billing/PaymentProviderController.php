<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Billing;

use App\Domains\PlatformBilling\Services\SaveTenantPaymentProvider;
use App\Http\Controllers\Api\V1\CapabilityController;
use App\Models\TenantPaymentProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The TENANT's own PayChangu credentials — used to RECEIVE payments from
 * parents in the portal. Keys are encrypted at rest; the portal never
 * returns them (only active/verified state).
 */
class PaymentProviderController extends CapabilityController
{
    public function show(): JsonResponse
    {
        $this->authorize('viewAny', TenantPaymentProvider::class);

        $provider = TenantPaymentProvider::query()->first();

        return $this->respond(new ProviderResource($provider));
    }

    public function update(Request $request, SaveTenantPaymentProvider $service): JsonResponse
    {
        $this->authorize('update', TenantPaymentProvider::class);

        $provider = $service->handle(
            (string) $request->string('secret_key')->toString(),
            $request->string('public_key')->toString() !== '' ? $request->string('public_key')->toString() : null,
            $request->string('mode')->toString(),
        );

        return $this->respond(new ProviderResource($provider));
    }
}
