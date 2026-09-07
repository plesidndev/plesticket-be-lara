<?php

namespace App\Enums;

enum CatalogStatus: string
{
    case Draft = 'draft';
    case WaitingForReview = 'waiting_for_review';
    case UnderReview = 'under_review';
    case ChangesRequested = 'changes_requested';
    case Approved = 'approved';
    case ProcessingDistribution = 'processing_distribution';
    case Scheduled = 'scheduled';
    case Live = 'live';
    case Rejected = 'rejected';

    public function editable(): bool
    {
        return in_array($this, [self::Draft, self::ChangesRequested], true);
    }

    public function next(): array
    {
        return match ($this) {
            self::WaitingForReview => [self::UnderReview, self::ChangesRequested, self::Rejected],
            self::UnderReview => [self::Approved, self::ChangesRequested, self::Rejected],
            self::Approved => [self::ProcessingDistribution, self::ChangesRequested, self::Rejected],
            self::ProcessingDistribution => [self::Scheduled, self::Live, self::ChangesRequested, self::Rejected],
            self::Scheduled => [self::Live, self::ChangesRequested, self::Rejected],
            self::Live => [self::ChangesRequested],
            default => [],
        };
    }
}
