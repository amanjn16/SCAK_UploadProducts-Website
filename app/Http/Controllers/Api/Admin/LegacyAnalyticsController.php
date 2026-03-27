<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\LegacyAnalyticsEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LegacyAnalyticsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $events = LegacyAnalyticsEvent::query()
            ->with('user')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();
                $query->where(function ($inner) use ($search): void {
                    $inner
                        ->where('event_type', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%')
                        ->orWhere('customer_name', 'like', '%'.$search.'%')
                        ->orWhere('customer_city', 'like', '%'.$search.'%')
                        ->orWhereHas('user', fn ($userQuery) => $userQuery->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->latest('occurred_at')
            ->paginate((int) $request->integer('per_page', 100))
            ->through(fn (LegacyAnalyticsEvent $event) => [
                'id' => $event->id,
                'phone' => $event->phone,
                'customer_name' => $event->customer_name ?: $event->user?->name,
                'customer_city' => $event->customer_city ?: $event->user?->city,
                'event_type' => $event->event_type,
                'ip_address' => $event->ip_address,
                'user_agent' => $event->user_agent,
                'event_data' => $event->event_data,
                'is_legacy_import' => true,
                'occurred_at' => optional($event->occurred_at)?->toIso8601String(),
            ]);

        return response()->json($events);
    }
}
