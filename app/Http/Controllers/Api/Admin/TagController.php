<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\TagUpsertRequest;
use App\Models\Tag;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TagController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    public function index(Request $request): JsonResponse
    {
        $tags = Tag::query()
            ->withCount('products')
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->string('search')->toString().'%'))
            ->orderBy('name')
            ->get()
            ->map(fn (Tag $tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
                'products_count' => $tag->products_count,
            ]);

        return response()->json(['data' => $tags]);
    }

    public function store(TagUpsertRequest $request): JsonResponse
    {
        $normalizedName = $this->normalizeTagName($request->string('name')->toString());

        $tag = Tag::query()->create([
            'name' => $normalizedName,
            'slug' => Str::slug($normalizedName),
        ]);

        $this->auditLogService->record('tag.created', $request->user(), $tag, ['name' => $tag->name], $request);

        return response()->json([
            'message' => 'Tag created successfully.',
            'data' => [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
                'products_count' => 0,
            ],
        ], 201);
    }

    public function update(TagUpsertRequest $request, Tag $tag): JsonResponse
    {
        $normalizedName = $this->normalizeTagName($request->string('name')->toString());

        $tag->update([
            'name' => $normalizedName,
            'slug' => Str::slug($normalizedName),
        ]);

        $this->auditLogService->record('tag.updated', $request->user(), $tag, ['name' => $tag->name], $request);

        return response()->json([
            'message' => 'Tag updated successfully.',
            'data' => [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
                'products_count' => $tag->products()->count(),
            ],
        ]);
    }

    public function destroy(Request $request, Tag $tag): JsonResponse
    {
        $meta = [
            'name' => $tag->name,
            'products_count' => $tag->products()->count(),
        ];
        $tag->products()->detach();
        $tag->delete();

        $this->auditLogService->record('tag.deleted', $request->user(), null, $meta, $request);

        return response()->json(['message' => 'Tag deleted successfully.']);
    }

    private function normalizeTagName(string $name): string
    {
        return Str::of($name)
            ->trim()
            ->squish()
            ->lower()
            ->title()
            ->toString();
    }
}
