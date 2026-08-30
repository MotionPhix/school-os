<?php

declare(strict_types=1);

namespace App\Domains\Academics\Events;

use App\Models\Subject;
use App\Support\Events\BusinessEvent;

final class SubjectUpdated extends BusinessEvent
{
    public function __construct(public readonly Subject $subject)
    {
        parent::__construct($subject->tenant_id);
    }

    public function name(): string
    {
        return 'academics.subject.updated';
    }

    public function payload(): array
    {
        return [
            'subject_id' => $this->subject->id,
            'code' => $this->subject->code,
            'category' => $this->subject->category->value,
        ];
    }
}
