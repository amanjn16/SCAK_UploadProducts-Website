<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaleCatalogProductRequest;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\ProductUpsertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SaleCatalogController extends Controller
{
    private const SOURCE = 'sale_scak';

    public function __construct(private readonly ProductUpsertService $products) {}

    public function upsert(SaleCatalogProductRequest $request): JsonResponse
    {
        $this->authorizeRequest($request->bearerToken());
        $payload = $request->validated();

        $product = Product::query()
            ->where('source_system', self::SOURCE)
            ->where('source_id', $payload['source_id'])
            ->first();

        $product = $this->products->upsert([
            'name' => $payload['title'],
            'price' => $payload['price'],
            'remarks' => $payload['remarks'] ?? null,
            'status' => $payload['status'],
            'tags' => array_values(array_filter(['Sale', $payload['brand'] ?? null])),
        ], $product);

        $product->forceFill([
            'source_system' => self::SOURCE,
            'source_id' => $payload['source_id'],
        ])->save();

        $this->syncImages($product, $payload['images']);

        return response()->json([
            'message' => 'Sale product synchronized.',
            'product_id' => $product->id,
            'sku' => $product->sku,
        ]);
    }

    public function archive(string $sourceId): JsonResponse
    {
        $this->authorizeRequest(request()->bearerToken());

        $product = Product::query()
            ->where('source_system', self::SOURCE)
            ->where('source_id', $sourceId)
            ->first();

        if ($product) {
            $this->products->upsert([
                'name' => $product->name,
                'price' => $product->price,
                'remarks' => $product->remarks,
                'status' => 'archived',
                'tags' => $product->tags()->pluck('name')->all(),
            ], $product);
        }

        return response()->json(['message' => 'Sale product archived.']);
    }

    private function authorizeRequest(?string $providedToken): void
    {
        $expectedToken = (string) config('scak.integrations.sale.token');

        abort_unless(
            $expectedToken !== '' && is_string($providedToken) && hash_equals($expectedToken, $providedToken),
            401,
            'Invalid integration token.'
        );
    }

    private function syncImages(Product $product, array $images): void
    {
        $sourceIds = collect($images)->pluck('source_id')->map(fn ($id) => (string) $id);
        $obsolete = $product->images()
            ->where('source_system', self::SOURCE)
            ->whereNotIn('source_id', $sourceIds)
            ->get();

        foreach ($obsolete as $image) {
            $this->products->deleteImage($product, $image);
        }

        foreach (array_values($images) as $index => $imagePayload) {
            $image = ProductImage::query()
                ->where('source_system', self::SOURCE)
                ->where('source_id', (string) $imagePayload['source_id'])
                ->first();

            if (! $image) {
                $raw = $this->downloadSaleImage($imagePayload['url']);
                $stored = $this->products->storeLegacyProductImage(
                    $product,
                    $raw,
                    basename((string) parse_url($imagePayload['url'], PHP_URL_PATH)) ?: 'sale-product.webp'
                );

                $image = ProductImage::query()->create([
                    'product_id' => $product->id,
                    'disk' => config('scak.storage.disk', 'products'),
                    'path' => $stored['path'],
                    'medium_path' => $stored['medium_path'],
                    'thumb_path' => $stored['thumb_path'],
                    'original_name' => basename((string) parse_url($imagePayload['url'], PHP_URL_PATH)) ?: 'sale-product.webp',
                    'mime_type' => $stored['mime_type'],
                    'bytes' => $stored['bytes'],
                    'sort_order' => $index + 1,
                    'is_cover' => false,
                    'source_system' => self::SOURCE,
                    'source_id' => (string) $imagePayload['source_id'],
                ]);
            }

            abort_unless($image->product_id === $product->id, 409, 'Sale image already belongs to another product.');
            $image->forceFill([
                'sort_order' => $index + 1,
                'is_cover' => ! empty($imagePayload['is_cover']),
            ])->save();
        }

        if (! $product->images()->where('is_cover', true)->exists()) {
            $first = $product->images()->orderBy('sort_order')->first();
            if ($first) {
                $this->products->markCoverImage($product, $first->id);
            }
        }

        $this->products->syncSearchText($product->load(['tags', 'sizes', 'features']));
    }

    private function downloadSaleImage(string $url): string
    {
        abort_unless(Str::lower((string) parse_url($url, PHP_URL_HOST)) === 'sale.scak.in', 422, 'Invalid Sale image host.');

        $response = Http::timeout(30)
            ->connectTimeout(10)
            ->retry(2, 250)
            ->withUserAgent('SCAK-Main-Sale-Sync/1.0')
            ->get($url);

        abort_unless($response->successful(), 422, 'Sale image could not be downloaded.');
        abort_unless(Str::startsWith(Str::lower($response->header('Content-Type', '')), 'image/'), 422, 'Sale URL is not an image.');
        abort_unless(strlen($response->body()) <= 20 * 1024 * 1024, 422, 'Sale image is too large.');

        return $response->body();
    }
}
