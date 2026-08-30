<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Finance;

use App\Domains\Finance\Services\BulkFinanceAction;
use App\Domains\Finance\Services\ToggleFeeStructure;
use App\Domains\Finance\Services\WriteFeeStructure;
use App\Http\Controllers\Api\V1\CapabilityController;
use App\Http\Requests\Api\V1\Finance\BulkFeeStructuresRequest;
use App\Http\Requests\Api\V1\Finance\StoreFeeStructureRequest;
use App\Http\Requests\Api\V1\Finance\ToggleFeeStructureRequest;
use App\Http\Requests\Api\V1\Finance\UpdateFeeStructureRequest;
use App\Http\Resources\Api\V1\Finance\FeeStructureResource;
use App\Models\FeeStructure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class FeeStructureController extends CapabilityController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', FeeStructure::class);

        $q = FeeStructure::query();
        if ($category = $request->string('category')->toString()) {
            $q->where('category', $category);
        }
        if ($request->has('is_active')) {
            $q->where('is_active', $request->boolean('is_active'));
        }
        if ($grade = $request->string('grade_label')->toString()) {
            $q->where('grade_label', $grade);
        }
        if ($cycle = $request->string('cycle')->toString()) {
            $q->where('cycle', $cycle);
        }
        if ($needle = $request->string('search')->toString()) {
            $q->where(function ($sub) use ($needle) {
                $sub->where('name', 'like', "%{$needle}%")
                    ->orWhere('grade_label', 'like', "%{$needle}%");
            });
        }

        $paginator = $q->orderBy('grade_label')->orderBy('name')
            ->paginate((int) $request->integer('per_page', 50));

        return $this->respondPaginated(FeeStructureResource::collection($paginator), $paginator);
    }

    public function show(FeeStructure $feeStructure): JsonResponse
    {
        $this->authorize('view', $feeStructure);

        return $this->respond(new FeeStructureResource($feeStructure));
    }

    public function store(StoreFeeStructureRequest $request, WriteFeeStructure $service): JsonResponse
    {
        $fee = $service->create($request->validated());

        return $this->respondCreated(new FeeStructureResource($fee));
    }

    public function update(UpdateFeeStructureRequest $request, FeeStructure $feeStructure, WriteFeeStructure $service): JsonResponse
    {
        $fee = $service->update($feeStructure, $request->validated());

        return $this->respond(new FeeStructureResource($fee));
    }

    public function toggle(ToggleFeeStructureRequest $request, FeeStructure $feeStructure, ToggleFeeStructure $service): JsonResponse
    {
        $fee = $service->handle($feeStructure, $request->boolean('is_active'));

        return $this->respond(new FeeStructureResource($fee));
    }

    public function destroy(FeeStructure $feeStructure): JsonResponse
    {
        $this->authorize('delete', $feeStructure);
        $feeStructure->delete();

        return $this->respondNoContent();
    }

    /** POST /finance/fees/bulk — activate | deactivate | delete. */
    public function bulk(BulkFeeStructuresRequest $request, BulkFinanceAction $service): JsonResponse
    {
        $data = $request->validated();

        // Authorize every row against the ability matching the requested
        // action (activate/deactivate -> update, delete -> delete).
        $fees = FeeStructure::query()->whereIn('id', $data['ids'])->get();

        foreach ($fees as $fee) {
            $this->authorize($data['action'] === 'delete' ? 'delete' : 'update', $fee);
        }

        return response()->json(['data' => $service->fees($fees->pluck('id')->all(), $data['action'])]);
    }
}
