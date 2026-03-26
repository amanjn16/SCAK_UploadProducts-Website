<?php

use App\Http\Controllers\Api\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Api\Admin\OrderRequestController as AdminOrderRequestController;
use App\Http\Controllers\Api\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Api\Admin\ProductImageController;
use App\Http\Controllers\Api\Admin\ProductPdfController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/admin/request-otp', [AdminAuthController::class, 'requestOtp']);
Route::post('/auth/admin/verify-otp', [AdminAuthController::class, 'verifyOtp']);

Route::middleware(['auth:sanctum', 'admin.role'])->prefix('admin')->group(function (): void {
    Route::get('/products', [AdminProductController::class, 'index']);
    Route::get('/order-requests', [AdminOrderRequestController::class, 'index']);
    Route::patch('/order-requests/{orderRequest}', [AdminOrderRequestController::class, 'update']);

    Route::post('/products', [AdminProductController::class, 'store']);
    Route::patch('/products/{product:id}', [AdminProductController::class, 'update']);
    Route::post('/products/{product:id}/images', [ProductImageController::class, 'store']);
    Route::post('/products/{product:id}/share-pdf', [ProductPdfController::class, 'store']);
});
