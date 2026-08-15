<?php

namespace App\Services;

use App\Models\VisitorSession;
use Illuminate\Http\Request;

class VisitorTrackingService
{
    public function track(Request $request): void
    {
        if (! $request->user()) {
            return;
        }

        $sessionKey = (string) $request->session()->getId();
        $now = now();
        $path = '/'.ltrim($request->path(), '/');
        $session = VisitorSession::query()->firstOrNew([
            'session_key' => $sessionKey,
            'user_id' => $request->user()->id,
        ]);

        if (! $session->exists) {
            $device = $this->parseUserAgent((string) $request->userAgent());

            $session->fill([
                'phone' => $request->user()->phone,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'device_type' => $device['device_type'],
                'browser' => $device['browser'],
                'os' => $device['os'],
                'entry_page' => $path,
                'referrer' => $request->headers->get('referer'),
                'started_at' => $now,
                'page_views' => 0,
            ]);
        }

        $startedAt = $session->started_at ?? $now;
        $lastPage = $session->current_page;
        $pageViews = (int) $session->page_views;

        $session->fill([
            'current_page' => $path,
            'last_activity_at' => $now,
            'duration_seconds' => max(0, $startedAt->diffInSeconds($now)),
            'page_views' => $lastPage === $path ? max(1, $pageViews) : $pageViews + 1,
        ]);

        $session->save();
    }

    protected function parseUserAgent(string $userAgent): array
    {
        $deviceType = preg_match('/tablet|ipad/i', $userAgent)
            ? 'tablet'
            : (preg_match('/mobile|android|iphone|ipod/i', $userAgent) ? 'mobile' : 'desktop');

        $browser = 'Unknown';
        $os = 'Unknown';

        if (preg_match('/Edg/i', $userAgent)) {
            $browser = 'Edge';
        } elseif (preg_match('/Chrome/i', $userAgent)) {
            $browser = 'Chrome';
        } elseif (preg_match('/Safari/i', $userAgent)) {
            $browser = 'Safari';
        } elseif (preg_match('/Firefox/i', $userAgent)) {
            $browser = 'Firefox';
        }

        if (preg_match('/Windows/i', $userAgent)) {
            $os = 'Windows';
        } elseif (preg_match('/Android/i', $userAgent)) {
            $os = 'Android';
        } elseif (preg_match('/iPhone|iPad|iPod/i', $userAgent)) {
            $os = 'iOS';
        } elseif (preg_match('/Macintosh|Mac OS/i', $userAgent)) {
            $os = 'macOS';
        } elseif (preg_match('/Linux/i', $userAgent)) {
            $os = 'Linux';
        }

        return [
            'device_type' => $deviceType,
            'browser' => $browser,
            'os' => $os,
        ];
    }
}
