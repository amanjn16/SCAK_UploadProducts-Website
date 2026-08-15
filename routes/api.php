<?php

use App\Http\Controllers\Api\Admin\ActivityLogController;
use App\Http\Controllers\Api\Admin\AdminUserController;
use App\Http\Controllers\Api\Admin\AppReleaseController;
use App\Http\Controllers\Api\Admin\CustomerController;
use App\Http\Controllers\Api\Admin\DailyCatalogController;
use App\Http\Controllers\Api\Admin\DuplicateProductController;
use App\Http\Controllers\Api\Admin\GeneratedExportController;
use App\Http\Controllers\Api\Admin\LegacyAnalyticsController;
use App\Http\Controllers\Api\Admin\OrderRequestController as AdminOrderRequestController;
use App\Http\Controllers\Api\Admin\ProductBatchController;
use App\Http\Controllers\Api\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Api\Admin\ProductDocumentController;
use App\Http\Controllers\Api\Admin\ProductFieldSettingController;
use App\Http\Controllers\Api\Admin\ProductImageController;
use App\Http\Controllers\Api\Admin\ProductPdfController;
use App\Http\Controllers\Api\Admin\ProductShareController;
use App\Http\Controllers\Api\Admin\StockFeedbackController;
use App\Http\Controllers\Api\Admin\StorefrontSettingsController;
use App\Http\Controllers\Api\Admin\SystemHealthController;
use App\Http\Controllers\Api\Admin\TagController;
use App\Http\Controllers\Api\Admin\VisitorSessionController;
use App\Http\Controllers\Api\MobileAdminController;
use App\Http\Controllers\Api\SaleCatalogController;
use Illuminate\Support\Facades\Route;

Route::prefix('mobile')->group(function (): void {
    Route::post('/auth/login', [MobileAdminController::class, 'login']);
    Route::middleware(['auth:sanctum', 'admin.role'])->group(function (): void {
        Route::get('/auth/me', [MobileAdminController::class, 'me']);
        Route::get('/brands', [MobileAdminController::class, 'brands']);
        Route::get('/products', [MobileAdminController::class, 'index']);
        Route::get('/products/stock-report', [MobileAdminController::class, 'stockReport']);
        Route::post('/products', [MobileAdminController::class, 'store']);
        Route::get('/products/{product:id}', [MobileAdminController::class, 'show']);
        Route::put('/products/{product:id}', [MobileAdminController::class, 'update']);
        Route::delete('/products/{product:id}', [MobileAdminController::class, 'destroy']);
        Route::put('/products/{product:id}/availability', [MobileAdminController::class, 'availability']);
        Route::post('/products/{product:id}/images/upload', [MobileAdminController::class, 'upload']);
        Route::post('/products/{product:id}/images/url', [MobileAdminController::class, 'addUrl']);
        Route::post('/products/{product:id}/images/{image}/cover', [MobileAdminController::class, 'cover']);
        Route::delete('/products/{product:id}/images/{image}', [MobileAdminController::class, 'deleteImage']);
        Route::get('/stock-feedback/suppliers', [StockFeedbackController::class, 'suppliers']);
        Route::post('/stock-feedback/suppliers/{supplier}/sessions', [StockFeedbackController::class, 'start']);
        Route::post('/stock-feedback/sessions/{stockSession}/items', [StockFeedbackController::class, 'addItem']);
        Route::patch('/stock-feedback/sessions/{stockSession}/items/{stockSessionItem}', [StockFeedbackController::class, 'updateItem']);
        Route::post('/stock-feedback/sessions/{stockSession}/submit', [StockFeedbackController::class, 'submit']);
        Route::post('/stock-feedback/items/{supplierStockItem}/photos', [StockFeedbackController::class, 'uploadPhoto']);
    });
});

Route::post('/integrations/sale/products', [SaleCatalogController::class, 'upsert']);
Route::delete('/integrations/sale/products/{sourceId}', [SaleCatalogController::class, 'archive']);

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
    Route::post('/products/{product:id}/images/url', [ProductImageController::class, 'storeUrls']);
    Route::delete('/products/{product:id}/images/{image}', [ProductImageController::class, 'destroy']);
    Route::post('/products/{product:id}/pdf', [ProductDocumentController::class, 'store']);
    Route::delete('/products/{product:id}/pdf', [ProductDocumentController::class, 'destroy']);
    Route::post('/products/{product:id}/share-pdf', [ProductPdfController::class, 'store']);
    Route::post('/products/share-pdf', [ProductPdfController::class, 'batchStore']);
    Route::post('/products/share-images', [ProductShareController::class, 'store']);
    Route::get('/product-batches', [ProductBatchController::class, 'index']);

    Route::get('/stock-feedback/suppliers', [StockFeedbackController::class, 'suppliers']);
    Route::post('/stock-feedback/suppliers/{supplier}/sessions', [StockFeedbackController::class, 'start']);
    Route::post('/stock-feedback/sessions/{stockSession}/items', [StockFeedbackController::class, 'addItem']);
    Route::patch('/stock-feedback/sessions/{stockSession}/items/{stockSessionItem}', [StockFeedbackController::class, 'updateItem']);
    Route::post('/stock-feedback/items/{supplierStockItem}/photos', [StockFeedbackController::class, 'uploadPhoto']);
    Route::post('/stock-feedback/sessions/{stockSession}/submit', [StockFeedbackController::class, 'submit']);

    Route::get('/settings/product-fields', [ProductFieldSettingController::class, 'index']);

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
    Route::get('/daily-catalog', [DailyCatalogController::class, 'show']);
    Route::post('/daily-catalog/generate', [DailyCatalogController::class, 'store']);
    Route::get('/settings/storefront', [StorefrontSettingsController::class, 'show']);
    Route::patch('/settings/storefront', [StorefrontSettingsController::class, 'update']);
    Route::get('/settings/app-releases', [AppReleaseController::class, 'show']);
});

Route::middleware(['auth:sanctum', 'admin.role', 'super.admin'])->prefix('admin')->group(function (): void {
    Route::get('/duplicate-products', [DuplicateProductController::class, 'index']);
    Route::post('/duplicate-products/merge', [DuplicateProductController::class, 'merge']);
    Route::delete('/duplicate-products/{product:id}', [DuplicateProductController::class, 'destroy']);
    Route::post('/products/bulk-delete', [AdminProductController::class, 'bulkDestroy']);
    Route::delete('/products/{product:id}', [AdminProductController::class, 'destroy']);
    Route::delete('/product-batches/{month}', [ProductBatchController::class, 'destroy']);
    Route::post('/admin-users', [AdminUserController::class, 'store']);
    Route::delete('/admin-users/{user}', [AdminUserController::class, 'destroy']);
    Route::patch('/settings/app-releases', [AppReleaseController::class, 'update']);
    Route::patch('/settings/product-fields/{productFieldSetting}', [ProductFieldSettingController::class, 'update']);
});
