<?php

use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\QuoteController;
use App\Http\Controllers\Api\StripeWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/locations', [CatalogController::class, 'locations']);
Route::get('/vehicle-types', [CatalogController::class, 'vehicleTypes']);
Route::get('/vehicles', [CatalogController::class, 'vehicles']);
Route::get('/vehicles/{vehicle:slug}', [CatalogController::class, 'vehicle']);

Route::post('/quote', QuoteController::class);
Route::post('/bookings', [BookingController::class, 'store']);

Route::get('/payment-config', [PaymentController::class, 'config']);
Route::post('/coupons/validate', [PaymentController::class, 'coupon']);
Route::get('/orders/{number}', [PaymentController::class, 'show']);
Route::post('/orders/{number}/pay', [PaymentController::class, 'pay']);
Route::post('/orders/{number}/confirm', [PaymentController::class, 'confirm']);
Route::post('/orders/{number}/cancel', [PaymentController::class, 'cancel']);

Route::post('/webhooks/stripe', StripeWebhookController::class);

Route::post('/contact', [\App\Http\Controllers\Api\ContactController::class, 'store']);
