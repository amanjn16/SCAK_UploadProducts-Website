<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use App\Services\ProductUpsertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

class MobileAdminController extends Controller
{
    public function __construct(private readonly ProductUpsertService $products) {}

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate(['username' => ['required', 'string'], 'password' => ['required', 'string']]);
        $user = User::query()->where('phone', $data['username'])->orWhere('email', $data['username'])->first();
        abort_unless($user?->isAdmin() && $user->is_active && filled($user->password) && Hash::check($data['password'], $user->password), 422, 'The login details are incorrect.');
        $user->tokens()->where('name', 'scak-admin-app')->delete();

        return response()->json(['token' => $user->createToken('scak-admin-app')->plainTextToken, 'user' => ['id' => $user->id, 'name' => $user->name]]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['id' => $request->user()->id, 'name' => $request->user()->name]);
    }

    public function brands(): JsonResponse
    {
        return response()->json(Product::query()->whereNotNull('brand')->distinct()->orderBy('brand')->pluck('brand')->map(fn (string $name) => ['id' => $this->brandId($name), 'name' => $name])->values());
    }

    public function index(Request $request): JsonResponse
    {
        $query = Product::query()->with(['images', 'coverImage', 'firstImage'])
            ->when($request->filled('search'), fn ($q) => $q->where('search_text', 'like', '%'.mb_strtolower($request->string('search')).'%'))
            ->when($request->filled('availability'), fn ($q) => $q->where('availability', $request->string('availability')))
            ->when($request->filled('brandId'), function ($q) use ($request): void {
                $brand = $this->brandName((int) $request->integer('brandId'));
                $q->when($brand, fn ($inner) => $inner->where('brand', $brand));
            })
            ->when($request->filled('productType'), fn ($q) => $q->where('product_type', $request->string('productType')))
            ->latest('updated_at');

        return response()->json($query->get()->map(fn (Product $product) => $this->payload($product))->values());
    }

    public function show(Product $product): JsonResponse
    {
        return response()->json($this->payload($product->load(['images', 'coverImage', 'firstImage'])));
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json($this->payload($this->save($request)), 201);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        return response()->json($this->payload($this->save($request, $product)));
    }

    public function availability(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate(['availability' => ['required', 'in:in_stock,out_of_stock']]);
        $product->update($data);

        return response()->json($this->payload($product->fresh()->load('images')));
    }

    public function upload(Request $request, Product $product): JsonResponse
    {
        $request->validate(['images' => ['required', 'array', 'max:36'], 'images.*' => ['image', 'max:20480']]);
        $before = $product->images()->count();
        $product = $this->products->addImages($product, $request->file('images', []));

        return response()->json(['product' => $this->payload($product), 'added' => array_fill(0, max(0, $product->images->count() - $before), true), 'errors' => []]);
    }

    public function addUrl(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate(['urls' => ['required', 'array', 'min:1', 'max:12'], 'urls.*' => ['required', 'url:http,https', 'max:2048']]);

        foreach ($data['urls'] as $url) {
            $host = (string) parse_url($url, PHP_URL_HOST);
            $addresses = gethostbynamel($host) ?: [];
            abort_if($addresses === [], 422, 'The image host could not be resolved.');
            foreach ($addresses as $address) {
                abort_if(filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false, 422, 'Private image hosts are not allowed.');
            }

            $response = Http::timeout(30)->connectTimeout(10)->retry(1, 250)->get($url);
            abort_unless($response->successful() && str_starts_with(mb_strtolower($response->header('Content-Type', '')), 'image/'), 422, 'The URL did not return an image.');
            abort_if(strlen($response->body()) > 20 * 1024 * 1024, 422, 'The image is too large.');
            $stored = $this->products->storeLegacyProductImage($product, $response->body(), basename((string) parse_url($url, PHP_URL_PATH)) ?: 'pasted-image.jpg');
            $product->images()->create([
                'disk' => config('scak.storage.disk', 'products'), 'path' => $stored['path'], 'medium_path' => $stored['medium_path'],
                'thumb_path' => $stored['thumb_path'], 'original_name' => basename((string) parse_url($url, PHP_URL_PATH)) ?: 'pasted-image.jpg',
                'mime_type' => $stored['mime_type'], 'bytes' => $stored['bytes'], 'sort_order' => ((int) $product->images()->max('sort_order')) + 1,
                'is_cover' => ! $product->images()->exists(),
            ]);
        }

        return response()->json(['product' => $this->payload($product->fresh()->load('images'))]);
    }

    public function cover(Product $product, ProductImage $image): JsonResponse
    {
        abort_unless($image->product_id === $product->id, 404);
        $this->products->markCoverImage($product, $image->id);

        return response()->json($this->payload($product->fresh()->load('images')));
    }

    public function deleteImage(Product $product, ProductImage $image): JsonResponse
    {
        return response()->json($this->payload($this->products->deleteImage($product, $image)));
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->products->delete($product);

        return response()->json(['message' => 'Product deleted.']);
    }

    public function stockReport(Request $request): JsonResponse
    {
        $request->validate(['availability' => ['required', 'in:in_stock,out_of_stock']]);
        $products = Product::query()->with('images')->where('availability', $request->string('availability'))->orderBy('name')->get();

        return response()->json(['products' => $products->map(fn (Product $product) => $this->payload($product))->values()]);
    }

    private function save(Request $request, ?Product $product = null): Product
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'], 'productType' => ['nullable', 'in:regular,sale'],
            'brandId' => ['nullable', 'integer'], 'brandName' => ['nullable', 'string', 'max:255'],
            'productCode' => ['nullable', 'string', 'max:255'], 'sellingPrice' => ['required', 'numeric', 'gt:0'],
            'brandPrice' => ['nullable', 'numeric', 'gte:0'], 'remarks' => ['nullable', 'string', 'max:5000'],
            'availability' => ['required', 'in:in_stock,out_of_stock'], 'published' => ['required', 'boolean'],
        ]);
        $brand = filled($data['brandName'] ?? null) ? $data['brandName'] : $this->brandName((int) ($data['brandId'] ?? 0));
        abort_unless(filled($brand), 422, 'Brand is required.');

        return $this->products->upsert([
            'name' => $data['name'], 'product_type' => $data['productType'] ?? $product?->product_type ?? 'regular',
            'brand' => $brand, 'sku' => $data['productCode'] ?? null, 'price' => $data['sellingPrice'],
            'brand_price' => $data['brandPrice'] ?? null, 'remarks' => $data['remarks'] ?? null,
            'availability' => $data['availability'], 'status' => $data['published'] ? 'active' : 'archived',
        ], $product);
    }

    private function payload(Product $product): array
    {
        $product->loadMissing('images');
        $cover = $product->images->firstWhere('is_cover', true) ?? $product->images->first();
        $complete = filled($product->name) && filled($product->brand) && $product->price > 0 && $cover;

        return [
            'id' => $product->id, 'name' => $product->name, 'productType' => $product->product_type,
            'productCode' => $product->sku, 'searchTerm' => $product->name, 'brandId' => $this->brandId((string) $product->brand),
            'brandName' => $product->brand, 'brandPrice' => $product->brand_price !== null ? (float) $product->brand_price : null,
            'sellingPrice' => (float) $product->price, 'remarks' => $product->remarks, 'availability' => $product->availability,
            'completionStatus' => $complete ? 'complete' : 'draft', 'published' => $product->status === 'active',
            'coverUrl' => $cover?->url ?? '', 'coverThumbnailUrl' => $cover?->thumb_url ?? '', 'updatedAt' => optional($product->updated_at)?->toIso8601String(),
            'images' => $product->images->map(fn (ProductImage $image) => ['id' => $image->id, 'url' => $image->url, 'thumbnailUrl' => $image->thumb_url, 'isCover' => $image->is_cover])->values(),
        ];
    }

    private function brandId(string $name): int
    {
        return (int) sprintf('%u', crc32(mb_strtolower($name)));
    }

    private function brandName(int $id): ?string
    {
        return Product::query()->whereNotNull('brand')->distinct()->pluck('brand')->first(fn (string $name) => $this->brandId($name) === $id);
    }
}
