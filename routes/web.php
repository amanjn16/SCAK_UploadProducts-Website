<?php

use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\CustomerAuthController;
use App\Http\Controllers\Api\FilterController;
use App\Http\Controllers\Api\OrderRequestController as CustomerOrderRequestController;
use App\Http\Controllers\Web\AdminAuthController;
use App\Http\Controllers\Web\AdminDashboardController;
use App\Http\Controllers\Web\AppDownloadsController;
use App\Http\Controllers\Web\DailyCatalogController;
use App\Http\Controllers\Web\ProductImageController;
use App\Http\Controllers\Web\ProductPdfController;
use App\Http\Controllers\Web\StorefrontController;
use Illuminate\Support\Facades\Route;

Route::get('/', [StorefrontController::class, 'catalog'])->name('landing');
Route::redirect('/login', '/admin/login')->name('login');
Route::get('/media/products/{image}', [ProductImageController::class, 'show'])->name('media.products.show');
Route::get('/media/products/{product:id}/pdf', [ProductPdfController::class, 'show'])->name('media.products.pdf');
Route::get('/catalog/daily.pdf', [DailyCatalogController::class, 'show'])->name('daily-catalog.show');
Route::redirect('/bucket', '/cart');
Route::post('/auth/customer/submit-phone', [CustomerAuthController::class, 'submitPhone'])->name('customer.auth.submit-phone');

Route::middleware(['track.visitor'])->group(function (): void {
    Route::get('/catalog', [StorefrontController::class, 'catalog'])->name('catalog');
    Route::get('/sale', [StorefrontController::class, 'sale'])->name('sale.catalog');
    Route::get('/catalog/{product:slug}', [StorefrontController::class, 'show'])->name('catalog.show');
    Route::redirect('/cart', '/catalog')->name('bucket');

    Route::get('/products', [CatalogController::class, 'index'])->name('products.index');
    Route::get('/products/{product:slug}', [CatalogController::class, 'show'])->name('products.show');
    Route::get('/filters', [FilterController::class, 'index'])->name('filters.index');
});

Route::post('/order-requests', [CustomerOrderRequestController::class, 'store'])->name('order-requests.store');

Route::get('/admin/login', [AdminAuthController::class, 'show'])->name('admin.login');
Route::post('/admin/auth/login', [AdminAuthController::class, 'login'])
    ->middleware('throttle:5,1')->name('admin.auth.login');
Route::post('/admin/auth/confirm-setup', [AdminAuthController::class, 'confirmSetup'])
    ->middleware('throttle:10,1')->name('admin.auth.confirm-setup');
Route::middleware(['auth', 'admin.role'])->group(function (): void {
    Route::get('/admin', AdminDashboardController::class)->name('admin.dashboard');
    Route::post('/admin/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');
    Route::get('/apps', [AppDownloadsController::class, 'index'])->name('apps.index');
});
Route::get('/apps/download/{platform}', [AppDownloadsController::class, 'download'])
    ->middleware('signed')
    ->name('apps.download');
