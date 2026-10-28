<?php

use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\RazorpayPaymentController;
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
Route::post('/webhooks/razorpay', [RazorpayPaymentController::class, 'webhook']);
Route::middleware('auth:api')->group(function () {
    Route::get('/addresses', [AddressController::class, 'index']);
    Route::post('/addresses', [AddressController::class, 'store']);
    Route::get('/addresses/{address}', [AddressController::class, 'show'])->whereNumber('address');
    Route::patch('/addresses/{address}', [AddressController::class, 'update'])->whereNumber('address');
    Route::delete('/addresses/{address}', [AddressController::class, 'destroy'])->whereNumber('address');
    Route::post('/addresses/{address}/default', [AddressController::class, 'makeDefault'])->whereNumber('address');
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
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{order}', [OrderController::class, 'show'])->whereNumber('order');
    Route::post('/payments/razorpay/create-order', [RazorpayPaymentController::class, 'createOrder']);
    Route::post('/payments/razorpay/verify', [RazorpayPaymentController::class, 'verify']);
    Route::post('/payments/razorpay/fail', [RazorpayPaymentController::class, 'fail']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

});
