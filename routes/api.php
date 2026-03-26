<?php

use App\Http\Controllers\Api\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Api\Admin\AdminUserController;
use App\Http\Controllers\Api\Admin\ActivityLogController;
use App\Http\Controllers\Api\Admin\CustomerController;
use App\Http\Controllers\Api\Admin\OrderRequestController as AdminOrderRequestController;
use App\Http\Controllers\Api\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Api\Admin\ProductImageController;
use App\Http\Controllers\Api\Admin\ProductPdfController;
use App\Http\Controllers\Api\Admin\ProductShareController;
use App\Http\Controllers\Api\Admin\TagController;
use App\Http\Controllers\Api\Admin\VisitorSessionController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/admin/request-otp', [AdminAuthController::class, 'requestOtp']);
Route::post('/auth/admin/verify-otp', [AdminAuthController::class, 'verifyOtp']);

Route::middleware(['auth:sanctum', 'admin.role'])->prefix('admin')->group(function (): void {
    Route::get('/products', [AdminProductController::class, 'index']);
    Route::get('/order-requests', [AdminOrderRequestController::class, 'index']);
    Route::patch('/order-requests/{orderRequest}', [AdminOrderRequestController::class, 'update']);

    Route::post('/products', [AdminProductController::class, 'store']);
    Route::post('/products/bulk-create', [AdminProductController::class, 'bulkStore']);
    Route::patch('/products/bulk-status', [AdminProductController::class, 'bulkStatus']);
    Route::patch('/products/{product:id}', [AdminProductController::class, 'update']);
    Route::post('/products/{product:id}/images', [ProductImageController::class, 'store']);
    Route::post('/products/{product:id}/share-pdf', [ProductPdfController::class, 'store']);
    Route::post('/products/share-pdf', [ProductPdfController::class, 'batchStore']);
    Route::post('/products/share-images', [ProductShareController::class, 'store']);

    Route::get('/tags', [TagController::class, 'index']);
    Route::post('/tags', [TagController::class, 'store']);
    Route::patch('/tags/{tag}', [TagController::class, 'update']);
    Route::delete('/tags/{tag}', [TagController::class, 'destroy']);

    Route::get('/admin-users', [AdminUserController::class, 'index']);
    Route::post('/admin-users', [AdminUserController::class, 'store']);
    Route::delete('/admin-users/{user}', [AdminUserController::class, 'destroy']);

    Route::get('/customers', [CustomerController::class, 'index']);
    Route::get('/customers/{user}', [CustomerController::class, 'show']);

    Route::get('/activity-logs', [ActivityLogController::class, 'index']);
    Route::get('/visitor-sessions', [VisitorSessionController::class, 'index']);
});
