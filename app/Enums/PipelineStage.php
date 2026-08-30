<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Admissions pipeline stages, ordered top-of-funnel to bottom.
 * Terminal stages: Enrolled, Rejected, Withdrawn — the pipeline UI
 * treats them as closed lanes.
 */
enum PipelineStage: string
{
    case Enquiry = 'enquiry';
    case Application = 'application';
    case Assessment = 'assessment';
    case Interview = 'interview';
    case Offer = 'offer';
    case Accepted = 'accepted';
    case Enrolled = 'enrolled';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    /** @return array<int, array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Enquiry => 'Enquiry',
            self::Application => 'Application',
            self::Assessment => 'Assessment',
            self::Interview => 'Interview',
            self::Offer => 'Offer',
            self::Accepted => 'Accepted',
            self::Enrolled => 'Enrolled',
            self::Rejected => 'Rejected',
            self::Withdrawn => 'Withdrawn',
        };
    }

    /**
     * Legal forward transitions out of this stage. Single source of truth
     * shared by AdvanceApplicationStage, SendOffer and BulkAdmissionsAction;
     * mirrors STAGE_TRANSITIONS in the frontend admissions verbs.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Enquiry => [self::Application, self::Rejected, self::Withdrawn],
            self::Application => [self::Assessment, self::Interview, self::Offer, self::Rejected, self::Withdrawn],
            self::Assessment => [self::Interview, self::Offer, self::Rejected, self::Withdrawn],
            self::Interview => [self::Offer, self::Rejected, self::Withdrawn],
            self::Offer => [self::Accepted, self::Rejected, self::Withdrawn],
            self::Accepted => [self::Enrolled, self::Withdrawn],
            self::Enrolled, self::Rejected, self::Withdrawn => [],
        };
    }

    public function canMoveTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Enrolled, self::Rejected, self::Withdrawn], true);
    }

    public function isOpen(): bool
    {
        return ! $this->isTerminal();
    }
}
