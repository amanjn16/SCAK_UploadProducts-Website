<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateDailyCatalogJob;
use App\Services\DailyCatalogService;
use Illuminate\Http\JsonResponse;

class DailyCatalogController extends Controller
{
    public function show(DailyCatalogService $service): JsonResponse
    {
        return response()->json(['data' => $service->metadata()]);
    }

    public function store(): JsonResponse
    {
        GenerateDailyCatalogJob::dispatchAfterResponse();

        return response()->json(['message' => 'Daily catalog generation started.'], 202);
    }
}
