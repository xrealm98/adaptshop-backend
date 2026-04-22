<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\StripeController;
use App\Http\Middleware\IsAdminMiddleware;


// Rutas públicas
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::apiResource('categories', CategoryController::class)->only(['index', 'show']);
Route::apiResource('products', ProductController::class)->only(['index', 'show']);


Route::post('/products/ids', [ProductController::class, 'getProductsIds']);


// Rutas protegidas por autenticación
Route::middleware('auth:sanctum')->group(function () {

    Route::post('/checkout/payment', [StripeController::class, 'createPaymentIntent']);
    Route::get('/profile', [UserController::class, 'showProfile']);
    Route::put('/profile', [UserController::class, 'updateProfile']);


    Route::apiResource('orders', OrderController::class)->only(['index', 'show', 'store']);

    Route::post('/logout', [AuthController::class, 'logout']);

    // Rutas administración
    Route::middleware(IsAdminMiddleware::class)->group(function () {
        Route::apiResource('categories', CategoryController::class)->except(['index', 'show']);
        Route::apiResource('products', ProductController::class)->except(['index', 'show']);
        Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus']);
        Route::apiResource('users', UserController::class)->except(['store']);
    });
});
