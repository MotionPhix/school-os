<?php

declare(strict_types=1);

namespace App\Domains\People\Events;

use App\Enums\PersonSubject;
use App\Support\Events\BusinessEvent;

final class PersonAvatarUpdated extends BusinessEvent
{
    public function __construct(
        string $tenantId,
        public readonly PersonSubject $subject,
        public readonly string $subjectId,
        public readonly ?string $avatarPath,
    ) {
        parent::__construct($tenantId);
    }

    public function name(): string
    {
        return 'people.avatar.updated';
    }

    public function payload(): array
    {
        return [
            'subject_type' => $this->subject->value,
            'subject_id' => $this->subjectId,
            'cleared' => $this->avatarPath === null,
        ];
    }
}
