<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StorefrontSettingsController extends Controller
{
    private const GROUP_LINKS_KEY = 'storefront_group_links';
    private const MARQUEE_SPEED_KEY = 'storefront_marquee_speed_seconds';

    public function show(): JsonResponse
    {
        return response()->json([
            'data' => [
                'group_links' => $this->groupLinks(),
                'marquee_speed_seconds' => $this->marqueeSpeedSeconds(),
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'group_links' => ['required', 'array', 'min:1'],
            'group_links.*.label' => ['required', 'string', 'max:80'],
            'group_links.*.url' => ['nullable', 'string', 'max:255'],
            'marquee_speed_seconds' => ['required', 'numeric', 'min:2', 'max:60'],
        ]);

        $groupLinks = collect($validated['group_links'])
            ->map(fn (array $entry) => [
                'label' => trim((string) $entry['label']),
                'url' => filled($entry['url'] ?? null) ? trim((string) $entry['url']) : null,
            ])
            ->filter(fn (array $entry) => filled($entry['label']))
            ->values()
            ->all();

        AppSetting::putArray(self::GROUP_LINKS_KEY, $groupLinks);
        AppSetting::put(self::MARQUEE_SPEED_KEY, round((float) $validated['marquee_speed_seconds'], 1));

        return response()->json([
            'message' => 'Storefront links updated successfully.',
            'data' => [
                'group_links' => $groupLinks,
                'marquee_speed_seconds' => $this->marqueeSpeedSeconds(),
            ],
        ]);
    }

    public static function defaultGroupLinks(): array
    {
        return config('scak.storefront.group_links', [
            ['label' => 'Wholesale Groups', 'url' => null],
            ['label' => 'Netra Groups', 'url' => null],
            ['label' => 'Readymade Group', 'url' => null],
        ]);
    }

    public static function groupLinks(): array
    {
        return AppSetting::getArray(self::GROUP_LINKS_KEY, self::defaultGroupLinks());
    }

    public static function marqueeSpeedSeconds(): float
    {
        $default = (float) config('scak.storefront.marquee_speed_seconds', 9.6);
        $stored = AppSetting::query()->where('key', self::MARQUEE_SPEED_KEY)->value('value');

        if ($stored === null || $stored === '') {
            return $default;
        }

        return round(max(2.0, min(60.0, (float) $stored)), 1);
    }
}
