<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\People;

use App\Enums\AttendanceStatus;
use App\Http\Resources\Api\V1\CapabilityResource;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * @mixin Student
 */
final class StudentResource extends CapabilityResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'campus_id' => $this->campus_id,
            'campus_name' => $this->whenLoaded('campus', fn () => $this->campus?->name),
            'admission_number' => $this->admission_number,
            'full_name' => $this->full_name,
            'preferred_name' => $this->preferred_name,
            'avatar_initials' => $this->avatar_initials,
            'avatar_url' => $this->avatarUrl(),
            'gender' => $this->gender->value,
            'date_of_birth' => optional($this->date_of_birth)?->toDateString(),
            'stage' => $this->stage->value,
            'grade_label' => $this->grade_label,
            'house' => $this->house,
            'status' => $this->status->value,
            'enrolled_on' => optional($this->enrolled_on)?->toDateString(),
            'guardians' => $this->whenLoaded('guardians', function () {
                return $this->guardians->map(fn ($g) => [
                    'guardian_id' => $g->id,
                    'guardian_name' => $g->full_name,
                    'relationship' => $g->pivot->relationship,
                    'is_primary' => (bool) $g->pivot->is_primary,
                ])->values();
            }, []),
            'documents' => PersonDocumentResource::collection(
                $this->whenLoaded('documents', fn () => $this->documents),
            ),
            // Cross-capability read models (Finance / Attendance). Values are
            // supplied by the `withMetrics` scope; fall back to a live read.
            'fees_balance' => $this->feesBalanceMinor(),
            'attendance_rate_30d' => $this->attendanceRate30d(),
            'updated_at' => $this->iso($this->updated_at),
        ];
    }

    private function feesBalanceMinor(): int
    {
        if ($this->invoices_sum_balance_minor !== null) {
            return (int) $this->invoices_sum_balance_minor;
        }

        return (int) $this->invoices()->sum('balance_minor');
    }

    private function attendanceRate30d(): int
    {
        if ($this->attendance_total_30d !== null) {
            $total = (int) $this->attendance_total_30d;
            $present = (int) $this->attendance_present_30d;
        } else {
            $since = now()->subDays(30)->toDateString();
            $marks = $this->attendanceMarks()
                ->whereHas('session', fn ($session) => $session->whereDate('date', '>=', $since));
            $total = (clone $marks)->count();
            $present = (clone $marks)->whereIn('status', [
                AttendanceStatus::Present->value,
                AttendanceStatus::Late->value,
            ])->count();
        }

        return $total > 0 ? (int) round(($present / $total) * 100) : 0;
    }

    private function avatarUrl(): ?string
    {
        if (! $this->avatar_path) {
            return null;
        }
        $disk = (string) config('people.media.disk', 'local');
        try {
            return Storage::disk($disk)->temporaryUrl($this->avatar_path, now()->addMinutes(60));
        } catch (Throwable) {
            return Storage::disk($disk)->url($this->avatar_path);
        }
    }
}
