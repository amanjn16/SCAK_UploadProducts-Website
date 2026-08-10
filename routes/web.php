<?php

use App\Http\Controllers\Api\CustomerAuthController;
use App\Http\Controllers\Api\FilterController;
use App\Http\Controllers\Api\OrderRequestController as CustomerOrderRequestController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Web\AppDownloadsController;
use App\Http\Controllers\Web\ProductImageController;
use App\Http\Controllers\Web\ProductPdfController;
use App\Http\Controllers\Web\StorefrontController;
use Illuminate\Support\Facades\Route;

Route::get('/', [StorefrontController::class, 'catalog'])->name('landing');
Route::redirect('/login', '/catalog');
Route::get('/media/products/{image}', [ProductImageController::class, 'show'])->name('media.products.show');
Route::get('/media/products/{product:id}/pdf', [ProductPdfController::class, 'show'])->name('media.products.pdf');
Route::redirect('/bucket', '/cart');
Route::post('/auth/customer/submit-phone', [CustomerAuthController::class, 'submitPhone'])->name('customer.auth.submit-phone');

Route::middleware(['track.visitor'])->group(function (): void {
    Route::get('/catalog', [StorefrontController::class, 'catalog'])->name('catalog');
    Route::get('/catalog/{product:slug}', [StorefrontController::class, 'show'])->name('catalog.show');
    Route::get('/cart', [StorefrontController::class, 'bucket'])->name('bucket');

    Route::get('/products', [CatalogController::class, 'index'])->name('products.index');
    Route::get('/products/{product:slug}', [CatalogController::class, 'show'])->name('products.show');
    Route::get('/filters', [FilterController::class, 'index'])->name('filters.index');
});

Route::post('/order-requests', [CustomerOrderRequestController::class, 'store'])->name('order-requests.store');

Route::redirect('/admin', '/catalog');
Route::redirect('/apps', '/catalog');

Route::get('/apps/download/{platform}', [AppDownloadsController::class, 'download'])
    ->middleware('signed')
    ->name('apps.download');
