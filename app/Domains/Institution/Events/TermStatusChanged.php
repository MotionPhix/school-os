<?php

declare(strict_types=1);

namespace App\Domains\Institution\Events;

use App\Models\Term;
use App\Support\Events\BusinessEvent;

final class TermStatusChanged extends BusinessEvent
{
    public function __construct(
        public readonly Term $term,
        public readonly string $from,
        public readonly string $to,
    ) {
        parent::__construct($term->tenant_id);
    }

    public function name(): string
    {
        return 'institution.term.status_changed';
    }

    public function payload(): array
    {
        return [
            'term_id' => $this->term->id,
            'academic_year_id' => $this->term->academic_year_id,
            'name' => $this->term->name,
            'from' => $this->from,
            'to' => $this->to,
        ];
    }
}
