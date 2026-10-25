<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\WishlistController;
use Illuminate\Support\Facades\Route;

Route::get('/test', function () {
    return response()->json([
        'message' => 'GreenBasket API is working',
    ]);
});
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{product}', [ProductController::class, 'show']);
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{category}', [CategoryController::class, 'show']);
Route::post('/refresh', [AuthController::class, 'refresh'])->middleware('throttle:60,1');
Route::middleware('auth:api')->group(function () {
    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart/items', [CartController::class, 'store']);
    Route::patch('/cart/items/{product}', [CartController::class, 'update'])->whereNumber('product');
    Route::delete('/cart/items/{product}', [CartController::class, 'destroy'])->whereNumber('product');
    Route::delete('/cart', [CartController::class, 'clear']);
    Route::post('/cart/merge', [CartController::class, 'merge']);
    Route::get('/wishlist', [WishlistController::class, 'index']);
    Route::post('/wishlist/items', [WishlistController::class, 'store']);
    Route::delete('/wishlist/items/{product}', [WishlistController::class, 'destroy'])->whereNumber('product');
    Route::delete('/wishlist', [WishlistController::class, 'clear']);
    Route::post('/wishlist/merge', [WishlistController::class, 'merge']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

});
