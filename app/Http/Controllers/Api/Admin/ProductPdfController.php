<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateProductPdfRequest;
use App\Models\Product;
use App\Services\AuditLogService;
use App\Services\ProductPdfService;
use Illuminate\Http\JsonResponse;

class ProductPdfController extends Controller
{
    public function __construct(
        private readonly ProductPdfService $productPdfService,
        private readonly AuditLogService $auditLogService,
    ) {}

    public function store(GenerateProductPdfRequest $request, Product $product): JsonResponse
    {
        $productIds = collect($request->validated('product_ids', [$product->id]))
            ->prepend($product->id)
            ->unique()
            ->values();

        $products = Product::query()
            ->with(['supplier', 'city', 'category', 'images'])
            ->whereIn('id', $productIds)
            ->get();

        $pdf = $this->productPdfService->generate($products);

        $this->auditLogService->record('product.share_pdf', $request->user(), null, [
            'product_ids' => $products->pluck('id')->values(),
        ], $request);

        return response()->json([
            'message' => 'PDF generated successfully.',
            'data' => $pdf,
        ]);
    }

    public function batchStore(GenerateProductPdfRequest $request): JsonResponse
    {
        $productIds = collect($request->validated('product_ids', []))->unique()->values();
        $products = Product::query()
            ->with(['supplier', 'city', 'category', 'images', 'tags'])
            ->whereIn('id', $productIds)
            ->get();

        $pdf = $this->productPdfService->generate($products);

        $this->auditLogService->record('product.share_pdf', $request->user(), null, [
            'product_ids' => $products->pluck('id')->values(),
        ], $request);

        return response()->json([
            'message' => 'PDF generated successfully.',
            'data' => $pdf,
        ]);
    }
}
