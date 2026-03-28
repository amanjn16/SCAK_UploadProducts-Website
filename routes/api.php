<?php

use App\Http\Controllers\Api\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Api\Admin\AdminUserController;
use App\Http\Controllers\Api\Admin\ActivityLogController;
use App\Http\Controllers\Api\Admin\CustomerController;
use App\Http\Controllers\Api\Admin\GeneratedExportController;
use App\Http\Controllers\Api\Admin\LegacyAnalyticsController;
use App\Http\Controllers\Api\Admin\OrderRequestController as AdminOrderRequestController;
use App\Http\Controllers\Api\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Api\Admin\ProductBatchController;
use App\Http\Controllers\Api\Admin\ProductDocumentController;
use App\Http\Controllers\Api\Admin\ProductImageController;
use App\Http\Controllers\Api\Admin\ProductPdfController;
use App\Http\Controllers\Api\Admin\ProductShareController;
use App\Http\Controllers\Api\Admin\TagController;
use App\Http\Controllers\Api\Admin\VisitorSessionController;
use App\Http\Controllers\Api\Admin\SystemHealthController;
use App\Http\Controllers\Api\Admin\StorefrontSettingsController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/admin/request-otp', [AdminAuthController::class, 'requestOtp']);
Route::post('/auth/admin/verify-otp', [AdminAuthController::class, 'verifyOtp']);

Route::middleware(['auth:sanctum', 'admin.role'])->prefix('admin')->group(function (): void {
    Route::get('/products', [AdminProductController::class, 'index']);
    Route::get('/products/{product:id}', [AdminProductController::class, 'show']);
    Route::get('/order-requests', [AdminOrderRequestController::class, 'index']);
    Route::patch('/order-requests/{orderRequest}', [AdminOrderRequestController::class, 'update']);

    Route::post('/products', [AdminProductController::class, 'store']);
    Route::post('/products/bulk-create', [AdminProductController::class, 'bulkStore']);
    Route::patch('/products/bulk-status', [AdminProductController::class, 'bulkStatus']);
    Route::patch('/products/{product:id}', [AdminProductController::class, 'update']);
    Route::post('/products/{product:id}/images', [ProductImageController::class, 'store']);
    Route::post('/products/{product:id}/pdf', [ProductDocumentController::class, 'store']);
    Route::delete('/products/{product:id}/pdf', [ProductDocumentController::class, 'destroy']);
    Route::post('/products/{product:id}/share-pdf', [ProductPdfController::class, 'store']);
    Route::post('/products/share-pdf', [ProductPdfController::class, 'batchStore']);
    Route::post('/products/share-images', [ProductShareController::class, 'store']);
    Route::get('/product-batches', [ProductBatchController::class, 'index']);

    Route::get('/tags', [TagController::class, 'index']);
    Route::post('/tags', [TagController::class, 'store']);
    Route::patch('/tags/{tag}', [TagController::class, 'update']);
    Route::delete('/tags/{tag}', [TagController::class, 'destroy']);

    Route::get('/admin-users', [AdminUserController::class, 'index']);

    Route::get('/customers', [CustomerController::class, 'index']);
    Route::get('/customers/{user}', [CustomerController::class, 'show']);

    Route::get('/activity-logs', [ActivityLogController::class, 'index']);
    Route::get('/visitor-sessions', [VisitorSessionController::class, 'index']);
    Route::get('/legacy-analytics', [LegacyAnalyticsController::class, 'index']);
    Route::get('/generated-exports/{generatedExport}', [GeneratedExportController::class, 'show']);
    Route::get('/system-health', [SystemHealthController::class, 'show']);
    Route::get('/settings/storefront', [StorefrontSettingsController::class, 'show']);
    Route::patch('/settings/storefront', [StorefrontSettingsController::class, 'update']);
});

Route::middleware(['auth:sanctum', 'admin.role', 'super.admin'])->prefix('admin')->group(function (): void {
    Route::post('/products/bulk-delete', [AdminProductController::class, 'bulkDestroy']);
    Route::delete('/products/{product:id}', [AdminProductController::class, 'destroy']);
    Route::delete('/product-batches/{month}', [ProductBatchController::class, 'destroy']);
    Route::post('/admin-users', [AdminUserController::class, 'store']);
    Route::delete('/admin-users/{user}', [AdminUserController::class, 'destroy']);
});
