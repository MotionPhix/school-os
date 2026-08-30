<?php

declare(strict_types=1);

namespace App\Domains\People\Events;

use App\Models\PersonDocument;
use App\Support\Events\BusinessEvent;

final class PersonDocumentAttached extends BusinessEvent
{
    public function __construct(public readonly PersonDocument $document)
    {
        parent::__construct($document->tenant_id);
    }

    public function name(): string
    {
        return 'people.document.attached';
    }

    public function payload(): array
    {
        return [
            'document_id' => $this->document->id,
            'subject_type' => $this->document->subject_type,
            'subject_id' => $this->document->subject_id,
            'name' => $this->document->name,
            'size' => $this->document->size,
        ];
    }
}
