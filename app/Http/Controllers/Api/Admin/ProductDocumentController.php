<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadProductPdfRequest;
use App\Models\Product;
use App\Services\AuditLogService;
use App\Services\ProductUpsertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductDocumentController extends Controller
{
    public function __construct(
        private readonly ProductUpsertService $productUpsertService,
        private readonly AuditLogService $auditLogService,
    ) {}

    public function store(UploadProductPdfRequest $request, Product $product): JsonResponse
    {
        $product = $this->productUpsertService->storePdf(
            $product,
            $request->file('pdf'),
        );

        $this->auditLogService->record('product.pdf_uploaded', $request->user(), $product, [
            'pdf_name' => $product->pdf_name,
        ], $request);

        return response()->json([
            'message' => 'Product PDF uploaded successfully.',
            'data' => [
                'pdf_url' => $product->pdf_url,
                'pdf_name' => $product->pdf_name,
            ],
        ], 201);
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        $this->productUpsertService->deletePdf($product);

        $this->auditLogService->record('product.pdf_deleted', $request->user(), $product, [
            'product_id' => $product->id,
        ], $request);

        return response()->json([
            'message' => 'Product PDF removed successfully.',
        ]);
    }
}
