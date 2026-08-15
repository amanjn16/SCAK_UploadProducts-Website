<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\AppReleaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppReleaseController extends Controller
{
    public function __construct(
        private readonly AppReleaseService $appReleaseService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->appReleaseService->all($request),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'android.version_name' => ['required', 'string', 'max:40'],
            'android.version_code' => ['required', 'integer', 'min:1'],
            'android.notes' => ['nullable', 'string', 'max:2000'],
            'android.storage_path' => ['nullable', 'string', 'max:255'],
            'ios.version_name' => ['nullable', 'string', 'max:40'],
            'ios.build_number' => ['nullable', 'string', 'max:40'],
            'ios.notes' => ['nullable', 'string', 'max:2000'],
            'ios.external_url' => ['nullable', 'url', 'max:255'],
            'ios.storage_path' => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json([
            'message' => 'App releases updated successfully.',
            'data' => $this->appReleaseService->update($validated),
        ]);
    }
}

