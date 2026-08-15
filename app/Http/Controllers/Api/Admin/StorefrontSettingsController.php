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
    private const SHOP_ADDRESS_KEY = 'storefront_shop_address';
    private const SHOP_LOCATION_URL_KEY = 'storefront_shop_location_url';
    private const SHOP_HOURS_KEY = 'storefront_shop_hours';

    public function show(): JsonResponse
    {
        return response()->json([
            'data' => [
                'group_links' => $this->groupLinks(),
                'marquee_speed_seconds' => $this->marqueeSpeedSeconds(),
                'shop_address' => self::shopDetails()['address'],
                'shop_location_url' => self::shopDetails()['location_url'],
                'shop_hours' => self::shopDetails()['shop_hours'],
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
            'shop_address' => ['nullable', 'string', 'max:500'],
            'shop_location_url' => ['nullable', 'url', 'max:500'],
            'shop_hours' => ['nullable', 'string', 'max:160'],
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
        AppSetting::put(self::SHOP_ADDRESS_KEY, trim((string) ($validated['shop_address'] ?? '')));
        AppSetting::put(self::SHOP_LOCATION_URL_KEY, trim((string) ($validated['shop_location_url'] ?? '')));
        AppSetting::put(self::SHOP_HOURS_KEY, trim((string) ($validated['shop_hours'] ?? '')));

        return response()->json([
            'message' => 'Storefront links updated successfully.',
            'data' => [
                'group_links' => $groupLinks,
                'marquee_speed_seconds' => $this->marqueeSpeedSeconds(),
                'shop_address' => self::shopDetails()['address'],
                'shop_location_url' => self::shopDetails()['location_url'],
                'shop_hours' => self::shopDetails()['shop_hours'],
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
        $stored = AppSetting::get(self::MARQUEE_SPEED_KEY, $default);

        if ($stored === null || $stored === '') {
            return $default;
        }

        return round(max(2.0, min(60.0, (float) $stored)), 1);
    }

    public static function shopDetails(): array
    {
        return [
            'phone' => (string) config('scak.support.phone', '9350188297'),
            'address' => (string) AppSetting::get(self::SHOP_ADDRESS_KEY, config('scak.support.default_city', 'Delhi')),
            'location_url' => (string) AppSetting::get(self::SHOP_LOCATION_URL_KEY, ''),
            'shop_hours' => (string) AppSetting::get(self::SHOP_HOURS_KEY, ''),
        ];
    }
}
