<?php

declare(strict_types=1);

namespace App\Domains\Institution\Services;

use App\Domains\Institution\Events\CampusUpdated;
use App\Enums\CampusStatus;
use App\Models\Campus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Bulk campus operations backing the hardened campuses table.
 *
 * Guards mirror the single-record rules: the primary campus can never be
 * deleted or closed, because it anchors the institution's legal address.
 *
 * @phpstan-type BulkResult array{affected:int, skipped:array<int,string>}
 */
final class BulkCampusAction
{
    /**
     * @param  array<int,string>  $ids
     * @return BulkResult
     */
    public function setStatus(array $ids, CampusStatus $status): array
    {
        $campuses = Campus::query()->whereIn('id', $ids)->get();

        if ($campuses->isEmpty()) {
            throw ValidationException::withMessages(['ids' => 'No matching campuses.']);
        }

        $skipped = [];

        $affected = DB::transaction(function () use ($campuses, $status, &$skipped): int {
            $count = 0;

            foreach ($campuses as $campus) {
                if ($status === CampusStatus::Closed && $campus->is_primary) {
                    $skipped[] = "{$campus->name} is the primary campus and cannot be closed.";

                    continue;
                }

                if ($campus->status === $status) {
                    continue;
                }

                $campus->status = $status;
                $campus->save();
                CampusUpdated::dispatch($campus);
                $count++;
            }

            return $count;
        });

        return ['affected' => $affected, 'skipped' => $skipped];
    }

    /**
     * @param  array<int,string>  $ids
     * @return BulkResult
     */
    public function delete(array $ids): array
    {
        $campuses = Campus::query()->whereIn('id', $ids)->get();

        if ($campuses->isEmpty()) {
            throw ValidationException::withMessages(['ids' => 'No matching campuses.']);
        }

        $skipped = [];

        $affected = DB::transaction(function () use ($campuses, &$skipped): int {
            $count = 0;

            foreach ($campuses as $campus) {
                if ($campus->is_primary) {
                    $skipped[] = "{$campus->name} is the primary campus and cannot be deleted.";

                    continue;
                }

                $campus->delete();
                $count++;
            }

            return $count;
        });

        return ['affected' => $affected, 'skipped' => $skipped];
    }
}
