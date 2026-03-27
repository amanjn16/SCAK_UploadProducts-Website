<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\AuditLogService;
use App\Services\ProductUpsertService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductBatchController extends Controller
{
    public function __construct(
        private readonly ProductUpsertService $productUpsertService,
        private readonly AuditLogService $auditLogService,
    ) {}

    public function index(): JsonResponse
    {
        $batches = Product::query()
            ->withCount('images')
            ->get()
            ->map(function (Product $product): array {
                $monthSource = $product->legacy_published_at ?? $product->created_at ?? now();
                $month = CarbonImmutable::parse($monthSource)->startOfMonth();

                return [
                    'month_key' => $month->format('Y-m'),
                    'month_label' => $month->format('F Y'),
                    'product_id' => $product->id,
                    'images_count' => $product->images_count,
                    'is_active' => $product->is_active,
                ];
            })
            ->groupBy('month_key')
            ->map(function ($items, string $monthKey): array {
                $month = CarbonImmutable::createFromFormat('Y-m', $monthKey)->startOfMonth();

                return [
                    'month_key' => $monthKey,
                    'month_label' => $month->format('F Y'),
                    'products_count' => $items->count(),
                    'images_count' => (int) $items->sum('images_count'),
                    'active_count' => (int) $items->where('is_active', true)->count(),
                    'archived_count' => (int) $items->where('is_active', false)->count(),
                ];
            })
            ->sortByDesc('month_key')
            ->values();

        return response()->json([
            'data' => $batches,
        ]);
    }

    public function destroy(Request $request, string $month): JsonResponse
    {
        abort_unless(preg_match('/^\d{4}-\d{2}$/', $month) === 1, 404);

        $start = CarbonImmutable::createFromFormat('Y-m', $month)->startOfMonth();
        $end = $start->endOfMonth();

        $products = Product::query()->get()->filter(function (Product $product) use ($start, $end): bool {
            $monthSource = $product->legacy_published_at ?? $product->created_at;

            if (! $monthSource) {
                return false;
            }

            $date = CarbonImmutable::parse($monthSource);

            return $date->betweenIncluded($start, $end);
        })->values();

        $productIds = $products->pluck('id')->all();
        $imagesCount = $products->sum(fn (Product $product) => $product->images()->count());

        foreach ($products as $product) {
            $this->productUpsertService->delete($product);
        }

        $this->auditLogService->record('product.batch_month_deleted', $request->user(), null, [
            'month_key' => $month,
            'products_count' => count($productIds),
            'images_count' => $imagesCount,
            'product_ids' => $productIds,
        ], $request);

        return response()->json([
            'message' => count($productIds) > 0
                ? 'Products deleted for '.$start->format('F Y').'.'
                : 'No products found for '.$start->format('F Y').'.',
        ]);
    }
}
