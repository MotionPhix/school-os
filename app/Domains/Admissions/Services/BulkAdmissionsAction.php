<?php

declare(strict_types=1);

namespace App\Domains\Admissions\Services;

use App\Enums\PipelineStage;
use App\Models\Application;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Bulk pipeline operations for the admissions board.
 *
 * Every row is applied through AdvanceApplicationStage so the timeline and
 * admissions.stage.changed events keep firing per application. Rows that
 * fail a StageTransitionGuard check are skipped with a reason rather than
 * aborting the whole batch.
 *
 * @phpstan-type BulkResult array{affected:int, skipped:array<int,string>}
 */
final class BulkAdmissionsAction
{
    public function __construct(
        private readonly AdvanceApplicationStage $advance,
        private readonly StageTransitionGuard $guard,
    ) {}

    /**
     * @param  array<int,string>  $ids
     * @return BulkResult
     */
    public function advanceStage(array $ids, PipelineStage $to, ?string $note, User $actor): array
    {
        $applications = $this->fetch($ids);
        $skipped = [];
        $affected = 0;

        foreach ($applications as $application) {
            $reason = $this->guard->reason($application, $to);

            if ($reason !== null) {
                $skipped[] = "{$application->reference}: {$reason}";

                continue;
            }

            try {
                $this->advance->handle($application, $to, $note, $actor);
                $affected++;
            } catch (HttpException $e) {
                $skipped[] = "{$application->reference}: ".($e->getMessage() ?: 'Rejected.');
            }
        }

        return ['affected' => $affected, 'skipped' => $skipped];
    }

    /**
     * @param  array<int,string>  $ids
     * @return BulkResult
     */
    public function reject(array $ids, ?string $note, User $actor): array
    {
        return $this->advanceStage($ids, PipelineStage::Rejected, $note ?? 'Rejected in bulk', $actor);
    }

    /**
     * @param  array<int,string>  $ids
     * @return BulkResult
     */
    public function withdraw(array $ids, ?string $note, User $actor): array
    {
        return $this->advanceStage($ids, PipelineStage::Withdrawn, $note ?? 'Withdrawn in bulk', $actor);
    }

    /**
     * @param  array<int,string>  $ids
     * @return Collection<int,Application>
     */
    private function fetch(array $ids): Collection
    {
        $rows = Application::query()->whereIn('id', $ids)->with('currentOffer')->get();

        if ($rows->isEmpty()) {
            throw ValidationException::withMessages(['ids' => 'No matching applications.']);
        }

        return $rows;
    }
}
