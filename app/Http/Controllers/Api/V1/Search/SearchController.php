<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Search;

use App\Http\Controllers\Api\V1\CapabilityController;
use App\Models\Announcement;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Unified global search (Discovery, handbook Ch. 38): one query, typed
 * results per resource. Every resource is permission-gated and
 * tenant-scoped; resources the caller cannot read are omitted from the
 * payload entirely.
 */
final class SearchController extends CapabilityController
{
    private const LIMIT_PER_RESOURCE = 8;

    public function index(Request $request, TenantContext $tenant): JsonResponse
    {
        $q = mb_trim($request->string('q')->toString());
        $tenantId = $tenant->id();

        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['data' => []]);
        }

        if ($q === '' || $tenantId === null) {
            return response()->json(['data' => []]);
        }

        $data = [];

        if ($user->can('viewAny', Student::class)) {
            $data['students'] = $this->map(
                Student::search($q)->where('tenant_id', $tenantId)->take(self::LIMIT_PER_RESOURCE)->get(),
                fn (Student $student): array => [
                    'id' => $student->id,
                    'label' => $student->full_name,
                    'subtitle' => "{$student->grade_label} · {$student->admission_number}",
                ],
            );
        }

        if ($user->can('viewAny', User::class)) {
            $users = User::search($q)
                ->take(self::LIMIT_PER_RESOURCE * 2)
                ->get()
                ->filter(fn (User $candidate): bool => $candidate->memberships()
                    ->where('tenants.id', $tenantId)
                    ->exists())
                ->values()
                ->take(self::LIMIT_PER_RESOURCE);

            $data['users'] = array_values($users->map(fn (User $user): array => [
                'id' => $user->id,
                'label' => $user->name,
                'subtitle' => $user->email,
            ])->all());
        }

        if ($user->can('viewAny', Invoice::class)) {
            $data['invoices'] = $this->map(
                Invoice::search($q)->where('tenant_id', $tenantId)->take(self::LIMIT_PER_RESOURCE)->get(),
                fn (Invoice $invoice): array => [
                    'id' => $invoice->id,
                    'label' => $invoice->number,
                    'subtitle' => $invoice->student_name,
                ],
            );
        }

        if ($user->can('viewAny', Announcement::class)) {
            $data['announcements'] = $this->map(
                Announcement::search($q)->where('tenant_id', $tenantId)->take(self::LIMIT_PER_RESOURCE)->get(),
                fn (Announcement $announcement): array => [
                    'id' => $announcement->id,
                    'label' => $announcement->title,
                    'subtitle' => $announcement->audience_label,
                ],
            );
        }

        return response()->json(['data' => $data]);
    }

    /**
     * @template T of Model
     *
     * @param  Collection<int, T>  $models
     * @param  callable(T): array{id: string, label: string, subtitle: string}  $mapper
     * @return list<array{id: string, label: string, subtitle: string}>
     */
    private function map(Collection $models, callable $mapper): array
    {
        return array_values($models->map($mapper)->all());
    }
}
