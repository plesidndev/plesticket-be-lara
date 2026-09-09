<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::query()->latest('created_at')->latest('id');

        if ($request->filled('action')) {
            $query->where('action', $request->query('action'));
        }

        if ($request->filled('actor')) {
            $query->where('actor_name', 'like', '%'.$request->query('actor').'%');
        }

        if ($request->filled('subject_type')) {
            $query->where('subject_type', $request->query('subject_type'));
        }

        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->query('subject_id'));
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->query('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->query('to'));
        }

        $paginator = $query->paginate((int) $request->query('limit', 20));

        return $this->paginated('Audit log retrieved.', AuditLogResource::collection($paginator), $paginator);
    }

    /** The distinct actions actually recorded, so the console filter offers only what exists. */
    public function actions(): JsonResponse
    {
        return $this->success('Audit actions retrieved.', AuditLog::query()->distinct()->orderBy('action')->pluck('action')->all());
    }
}
