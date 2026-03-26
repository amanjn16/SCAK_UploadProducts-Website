<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadProductImagesRequest;
use App\Models\Product;
use App\Services\AuditLogService;
use App\Services\ProductUpsertService;
use Illuminate\Http\JsonResponse;

class ProductImageController extends Controller
{
    public function __construct(
        private readonly ProductUpsertService $productUpsertService,
        private readonly AuditLogService $auditLogService,
    ) {}

    public function store(UploadProductImagesRequest $request, Product $product): JsonResponse
    {
        $product = $this->productUpsertService->addImages(
            $product,
            $request->file('images', []),
            $request->integer('cover_index'),
        );

        $this->auditLogService->record('product.images_uploaded', $request->user(), $product, [
            'images_count' => count($request->file('images', [])),
        ], $request);

        return response()->json([
            'message' => 'Product images uploaded successfully.',
            'images' => $product->images,
        ], 201);
    }
}
