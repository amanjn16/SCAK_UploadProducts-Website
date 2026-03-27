<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $logs = AuditLog::query()
            ->select(['id', 'user_id', 'action', 'meta', 'ip_address', 'user_agent', 'created_at'])
            ->with('user:id,name,phone')
            ->latest()
            ->paginate((int) $request->integer('per_page', 25))
            ->through(fn (AuditLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'meta' => $log->meta ?? [],
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'created_at' => optional($log->created_at)?->toIso8601String(),
                'user' => $log->user ? [
                    'id' => $log->user->id,
                    'name' => $log->user->name,
                    'phone' => $log->user->phone,
                ] : null,
            ]);

        return response()->json($logs);
    }
}
