<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StorefrontSettingsController extends Controller
{
    private const GROUP_LINKS_KEY = 'storefront_group_links';

    public function show(): JsonResponse
    {
        return response()->json([
            'data' => [
                'group_links' => $this->groupLinks(),
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'group_links' => ['required', 'array', 'min:1'],
            'group_links.*.label' => ['required', 'string', 'max:80'],
            'group_links.*.url' => ['nullable', 'string', 'max:255'],
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

        return response()->json([
            'message' => 'Storefront links updated successfully.',
            'data' => [
                'group_links' => $groupLinks,
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
}
