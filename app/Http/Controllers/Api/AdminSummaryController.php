<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Event;
use App\Models\Payment;
use App\Models\Talent;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class AdminSummaryController extends Controller
{
    use ApiResponse;

    public function __invoke(): JsonResponse
    {
        return $this->success('Admin summary retrieved.', [
            'pending_events' => Event::where('verification_status', 'pending')->count(),
            'verified_events' => Event::where('verification_status', 'verified')->count(),
            'active_users' => User::where('is_active', true)->count(),
            'active_categories' => Category::where('is_active', true)->count(),
            'pending_talents' => Talent::where('is_verified', false)->count(),
            'refunds_requiring_attention' => Payment::where('requires_refund', true)->count(),
            'webhooks_requiring_attention' => WebhookDelivery::whereIn('status', ['unmatched', 'failed'])->count(),
        ]);
    }
}
