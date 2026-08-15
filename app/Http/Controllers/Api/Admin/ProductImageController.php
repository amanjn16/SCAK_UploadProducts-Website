<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadProductImagesRequest;
use App\Models\Product;
use App\Services\AuditLogService;
use App\Services\ProductUpsertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\ProductImage;

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
            $request->boolean('watermarked'),
        );

        $this->auditLogService->record('product.images_uploaded', $request->user(), $product, [
            'images_count' => count($request->file('images', [])),
        ], $request);

        return response()->json([
            'message' => 'Product images uploaded successfully.',
            'images' => $product->images,
        ], 201);
    }

    public function storeUrls(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'urls' => ['required', 'array', 'min:1', 'max:12'],
            'urls.*' => ['required', 'url:http,https', 'max:2048'],
        ]);

        $added = 0;
        foreach ($data['urls'] as $url) {
            $host = (string) parse_url($url, PHP_URL_HOST);
            $addresses = gethostbynamel($host) ?: [];
            abort_if($addresses === [], 422, 'The image host could not be resolved.');
            foreach ($addresses as $address) {
                abort_if(filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false, 422, 'Private image hosts are not allowed.');
            }

            $response = Http::timeout(30)->connectTimeout(10)->retry(1, 250)->get($url);
            abort_unless($response->successful() && str_starts_with(mb_strtolower($response->header('Content-Type', '')), 'image/'), 422, 'A pasted URL did not return an image.');
            abort_if(strlen($response->body()) > 20 * 1024 * 1024, 422, 'A pasted image is too large.');

            $name = basename((string) parse_url($url, PHP_URL_PATH)) ?: 'pasted-image.jpg';
            $stored = $this->productUpsertService->storeLegacyProductImage($product, $response->body(), $name);
            $product->images()->create([
                'disk' => config('scak.storage.disk', 'products'),
                'path' => $stored['path'],
                'medium_path' => $stored['medium_path'],
                'thumb_path' => $stored['thumb_path'],
                'original_name' => $name,
                'mime_type' => $stored['mime_type'],
                'bytes' => $stored['bytes'],
                'sort_order' => ((int) $product->images()->max('sort_order')) + 1,
                'is_cover' => ! $product->images()->exists(),
            ]);
            $added++;
        }

        $this->auditLogService->record('product.image_urls_added', $request->user(), $product, [
            'images_count' => $added,
        ], $request);

        return response()->json([
            'message' => $added.' pasted image'.($added === 1 ? '' : 's').' added.',
            'images' => $product->fresh()->images,
        ], 201);
    }

    public function destroy(Request $request, Product $product, ProductImage $image): JsonResponse
    {
        $product = $this->productUpsertService->deleteImage($product, $image);

        $this->auditLogService->record('product.image_deleted', $request->user(), $product, [
            'image_id' => $image->id,
            'remaining_images' => $product->images->count(),
        ], $request);

        return response()->json([
            'message' => 'Product image deleted successfully.',
            'images' => $product->images,
        ]);
    }
}
