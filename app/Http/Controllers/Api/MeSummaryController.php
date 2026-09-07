<?php

namespace App\Http\Controllers\Api;

use App\Enums\CatalogStatus;
use App\Http\Controllers\Controller;
use App\Models\CatalogRelease;
use App\Models\Event;
use App\Models\Talent;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class MeSummaryController extends Controller
{
    use ApiResponse;

    /**
     * Counts for the signed-in creator's own workspace. Every figure is scoped
     * to this user, so an account with no organizer capability simply reports
     * zero events rather than needing a separate shape.
     */
    public function __invoke(): JsonResponse
    {
        $userId = auth('api')->id();

        return $this->success('Creator summary retrieved.', [
            // Work handed back to the creator: the only counts that are blocking.
            'releases_changes_requested' => CatalogRelease::where('user_id', $userId)->where('status', CatalogStatus::ChangesRequested)->count(),
            'releases_draft' => CatalogRelease::where('user_id', $userId)->where('status', CatalogStatus::Draft)->count(),
            'releases_in_review' => CatalogRelease::where('user_id', $userId)->whereIn('status', [CatalogStatus::WaitingForReview, CatalogStatus::UnderReview])->count(),
            'releases_live' => CatalogRelease::where('user_id', $userId)->where('status', CatalogStatus::Live)->count(),
            'talents_total' => Talent::where('submitted_by', $userId)->count(),
            'talents_awaiting_verification' => Talent::where('submitted_by', $userId)->where('is_verified', false)->count(),
            'events_total' => Event::where('user_id', $userId)->count(),
            'events_pending_review' => Event::where('user_id', $userId)->where('verification_status', 'pending')->count(),
        ]);
    }
}
