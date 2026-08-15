<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\GeneratedExport;
use Illuminate\Http\JsonResponse;

class GeneratedExportController extends Controller
{
    public function show(GeneratedExport $generatedExport): JsonResponse
    {
        return response()->json([
            'data' => [
                'id' => $generatedExport->id,
                'uuid' => $generatedExport->uuid,
                'type' => $generatedExport->type,
                'status' => $generatedExport->status,
                'result_url' => $generatedExport->result_url,
                'error_message' => $generatedExport->error_message,
                'completed_at' => optional($generatedExport->completed_at)?->toIso8601String(),
                'created_at' => optional($generatedExport->created_at)?->toIso8601String(),
            ],
        ]);
    }
}
