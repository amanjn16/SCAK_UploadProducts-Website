<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\VisitorSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VisitorSessionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $sessions = VisitorSession::query()
            ->select([
                'id',
                'user_id',
                'phone',
                'customer_name',
                'customer_city',
                'session_key',
                'current_page',
                'entry_page',
                'referrer',
                'page_views',
                'duration_seconds',
                'device_type',
                'browser',
                'os',
                'is_legacy_import',
                'started_at',
                'last_activity_at',
            ])
            ->with('user:id,name,city')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();
                $query->where(function ($inner) use ($search) {
                    $inner
                        ->where('phone', 'like', '%'.$search.'%')
                        ->orWhere('customer_name', 'like', '%'.$search.'%')
                        ->orWhere('customer_city', 'like', '%'.$search.'%')
                        ->orWhere('current_page', 'like', '%'.$search.'%')
                        ->orWhere('entry_page', 'like', '%'.$search.'%')
                        ->orWhereHas('user', fn ($userQuery) => $userQuery->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->latest('last_activity_at')
            ->paginate((int) $request->integer('per_page', 25))
            ->through(fn (VisitorSession $session) => [
                'id' => $session->id,
                'phone' => $session->phone,
                'customer_name' => $session->customer_name ?: $session->user?->name,
                'customer_city' => $session->customer_city ?: $session->user?->city,
                'session_key' => $session->session_key,
                'current_page' => $session->current_page,
                'entry_page' => $session->entry_page,
                'referrer' => $session->referrer,
                'page_views' => $session->page_views,
                'duration_seconds' => $session->duration_seconds,
                'device_type' => $session->device_type,
                'browser' => $session->browser,
                'os' => $session->os,
                'is_legacy_import' => $session->is_legacy_import,
                'started_at' => optional($session->started_at)?->toIso8601String(),
                'last_activity_at' => optional($session->last_activity_at)?->toIso8601String(),
            ]);

        return response()->json($sessions);
    }
}
