<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\GeneratedExport;
use App\Models\LegacyAnalyticsEvent;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Tag;
use App\Models\VisitorSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SystemHealthController extends Controller
{
    public function show(): JsonResponse
    {
        $disk = config('scak.storage.disk', 'products');
        $storagePath = Storage::disk($disk)->path('');

        return response()->json([
            'data' => [
                'products' => Product::query()->count(),
                'images' => ProductImage::query()->count(),
                'tags' => Tag::query()->count(),
                'activity_logs' => AuditLog::query()->count(),
                'visitor_sessions' => VisitorSession::query()->count(),
                'legacy_analytics_events' => LegacyAnalyticsEvent::query()->count(),
                'generated_exports' => [
                    'pending' => GeneratedExport::query()->where('status', GeneratedExport::STATUS_PENDING)->count(),
                    'processing' => GeneratedExport::query()->where('status', GeneratedExport::STATUS_PROCESSING)->count(),
                    'failed' => GeneratedExport::query()->where('status', GeneratedExport::STATUS_FAILED)->count(),
                ],
                'queue_jobs' => DB::table('jobs')->count(),
                'storage' => [
                    'disk' => $disk,
                    'root' => $storagePath,
                    'products_bytes' => $this->directorySize($storagePath),
                ],
            ],
        ]);
    }

    private function directorySize(string $directory): int
    {
        if (! is_dir($directory)) {
            return 0;
        }

        $size = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if ($item->isFile()) {
                $size += $item->getSize();
            }
        }

        return $size;
    }
}
