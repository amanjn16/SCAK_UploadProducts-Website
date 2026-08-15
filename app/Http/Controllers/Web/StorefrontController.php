<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Contracts\View\View;

class StorefrontController extends Controller
{
    public function landing()
    {
        if (auth()->check()) {
            return redirect()->route('catalog');
        }

        return redirect()->route('login');
    }

    public function catalog(): View
    {
        return view('storefront.catalog', [
            'customer' => auth()->user(),
            'catalogTitle' => 'Catalog',
            'catalogProductType' => null,
        ]);
    }

    public function sale(): View
    {
        return view('storefront.catalog', [
            'customer' => auth()->user(),
            'catalogTitle' => 'Sale Catalog',
            'catalogProductType' => 'sale',
        ]);
    }

    public function show(Product $product): View
    {
        return view('storefront.product', [
            'customer' => auth()->user(),
            'product' => $product->load(['supplier', 'city', 'category', 'topFabric', 'dupattaFabric', 'sizes', 'features', 'tags', 'images']),
        ]);
    }

    public function bucket(): View
    {
        return view('storefront.bucket', [
            'customer' => auth()->user(),
        ]);
    }
}
