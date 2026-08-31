<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CurrencyCode;
use App\Enums\InvoiceStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Laravel\Scout\Searchable;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $number
 * @property string $student_id
 * @property string $student_name
 * @property string $student_initials
 * @property string $grade_label
 * @property string $guardian_name
 * @property string $academic_year_label
 * @property string $term_label
 * @property Carbon $issued_on
 * @property Carbon $due_on
 * @property CurrencyCode $currency
 * @property int $subtotal_minor
 * @property int $discount_minor
 * @property int $total_minor
 * @property int $paid_minor
 * @property int $balance_minor
 * @property InvoiceStatus $status
 * @property-read Student $student
 * @property-read AcademicYear $academicYear
 * @property-read Term $term
 * @property-read Collection<int, InvoiceLine> $lines
 * @property-read Collection<int, Payment> $payments
 */
#[Fillable([
    'tenant_id', 'number', 'student_id', 'student_name', 'student_initials',
    'grade_label', 'guardian_name', 'academic_year_id', 'academic_year_label',
    'term_id', 'term_label', 'issued_on', 'due_on', 'currency',
    'subtotal_minor', 'discount_minor', 'total_minor', 'paid_minor',
    'balance_minor', 'status', 'last_reminded_at',
])]
final class Invoice extends Model
{
    use BelongsToTenant;
    use HasUuid;
    use Searchable;
    use SoftDeletes;

    protected $table = 'finance_invoices';

    /** @return array{number: string, student_name: string, guardian_name: string} */
    public function toSearchableArray(): array
    {
        return [
            'number' => $this->number,
            'student_name' => $this->student_name,
            'guardian_name' => $this->guardian_name,
        ];
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<AcademicYear, $this> */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /** @return BelongsTo<Term, $this> */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /** @return HasMany<InvoiceLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('position');
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderByDesc('received_at');
    }

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'currency' => CurrencyCode::class,
            'issued_on' => 'date',
            'due_on' => 'date',
            'subtotal_minor' => 'integer',
            'discount_minor' => 'integer',
            'total_minor' => 'integer',
            'paid_minor' => 'integer',
            'balance_minor' => 'integer',
            'last_reminded_at' => 'datetime',
        ];
    }

    #[Scope]
    protected function status(Builder $query, string $status): void
    {
        $query->where('status', $status);
    }

    #[Scope]
    protected function outstanding(Builder $query): void
    {
        $query->whereIn('status', [
            InvoiceStatus::Issued->value,
            InvoiceStatus::PartiallyPaid->value,
            InvoiceStatus::Overdue->value,
        ]);
    }

    #[Scope]
    protected function overdueAsOf(Builder $query, string $date): void
    {
        $query->outstanding()->whereDate('due_on', '<', $date);
    }
}
