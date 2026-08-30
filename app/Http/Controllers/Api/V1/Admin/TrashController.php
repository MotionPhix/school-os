<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\CapabilityController;
use App\Policies\TrashResource;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Operator trash management.
 *
 * Restores soft-deleted records within the active tenant. The resource
 * slug must be in the `admin.restoreable` whitelist; the record is looked
 * up including trashed rows and tenant-scoped, so a record from another
 * tenant is indistinguishable from one that does not exist. Only rows
 * that are actually archived (deleted_at set) are restored.
 */
final class TrashController extends CapabilityController
{
    public function restore(Request $request, string $resource, string $id): JsonResponse
    {
        /** @var mixed $configEntry */
        $configEntry = config("admin.restoreable.{$resource}");

        if (! is_string($configEntry) || ! is_subclass_of($configEntry, Model::class)) {
            return $this->respondNotFound();
        }

        /** @var class-string<Model> $modelClass */
        $modelClass = $configEntry;

        $this->authorize('restore', TrashResource::class);

        $tenantId = app(TenantContext::class)->id();
        if ($tenantId === null) {
            return $this->respondNotFound();
        }

        $restored = (bool) $modelClass::query()
            ->withoutGlobalScope(SoftDeletingScope::class)
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->whereNotNull('deleted_at')
            ->update(['deleted_at' => null]);

        if (! $restored) {
            return $this->respondNotFound();
        }

        return response()->json([
            'data' => [
                'restored' => true,
                'resource' => $resource,
                'id' => $id,
            ],
        ]);
    }

    private function respondNotFound(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Record not found.',
            'errors' => ['id' => ['No archived record exists for this resource.']],
        ], 404);
    }
}
