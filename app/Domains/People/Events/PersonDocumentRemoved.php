<?php

declare(strict_types=1);

namespace App\Domains\People\Events;

use App\Support\Events\BusinessEvent;

final class PersonDocumentRemoved extends BusinessEvent
{
    public function __construct(
        string $tenantId,
        public readonly string $documentId,
        public readonly string $subjectType,
        public readonly string $subjectId,
    ) {
        parent::__construct($tenantId);
    }

    public function name(): string
    {
        return 'people.document.removed';
    }

    public function payload(): array
    {
        return [
            'document_id' => $this->documentId,
            'subject_type' => $this->subjectType,
            'subject_id' => $this->subjectId,
        ];
    }
}
