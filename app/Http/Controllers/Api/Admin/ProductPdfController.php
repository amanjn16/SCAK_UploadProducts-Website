<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateProductPdfRequest;
use App\Jobs\GenerateProductPdfJob;
use App\Models\GeneratedExport;
use App\Models\Product;
use App\Services\AuditLogService;
use App\Services\ProductPdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

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

        if ($request->boolean('async')) {
            $generatedExport = GeneratedExport::query()->create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $request->user()?->id,
                'type' => 'product_pdf',
                'status' => GeneratedExport::STATUS_PENDING,
                'request_payload' => [
                    'product_ids' => $productIds->all(),
                ],
            ]);

            GenerateProductPdfJob::dispatchAfterResponse($generatedExport->id, $productIds->all());

            $this->auditLogService->record('product.share_pdf_queued', $request->user(), null, [
                'product_ids' => $productIds->values(),
                'generated_export_id' => $generatedExport->id,
            ], $request);

            return response()->json([
                'message' => 'PDF generation queued successfully.',
                'data' => [
                    'id' => $generatedExport->id,
                    'uuid' => $generatedExport->uuid,
                    'status' => $generatedExport->status,
                ],
            ], 202);
        }

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
