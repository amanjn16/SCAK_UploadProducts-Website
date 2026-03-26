<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\City;
use App\Models\Fabric;
use App\Models\Feature;
use App\Models\Size;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;

class FilterController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name', 'slug']),
            'cities' => City::query()->orderBy('name')->get(['id', 'name', 'slug']),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name', 'slug']),
            'top_fabrics' => Fabric::query()->whereIn('type', ['top', null])->orderBy('name')->get(['id', 'name', 'slug']),
            'dupatta_fabrics' => Fabric::query()->whereIn('type', ['dupatta', null])->orderBy('name')->get(['id', 'name', 'slug']),
            'sizes' => Size::query()->orderBy('name')->get(['id', 'name', 'slug']),
            'features' => Feature::query()->orderBy('name')->get(['id', 'name', 'slug']),
        ]);
    }
}
