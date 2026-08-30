<?php

declare(strict_types=1);

namespace App\Domains\Institution\Services;

use App\Domains\Institution\Events\CampusCreated;
use App\Domains\Institution\Events\CampusUpdated;
use App\Models\Campus;
use Illuminate\Support\Facades\DB;

final class WriteCampus
{
    /**
     * Create or update a campus. If `is_primary` becomes true, all
     * sibling campuses in the same tenant are demoted so exactly one
     * primary campus exists per tenant.
     *
     * @param  array<string,mixed>  $data
     */
    public function handle(array $data, ?Campus $existing = null): Campus
    {
        return DB::transaction(function () use ($data, $existing): Campus {
            $campus = $existing ?? new Campus;
            $campus->fill($data);
            $campus->save();

            if ($campus->is_primary) {
                Campus::query()
                    ->where('tenant_id', $campus->tenant_id)
                    ->where('id', '!=', $campus->id)
                    ->update(['is_primary' => false]);
            }

            $existing === null
                ? CampusCreated::dispatch($campus)
                : CampusUpdated::dispatch($campus);

            return $campus->fresh();
        });
    }
}
