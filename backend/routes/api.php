<?php

use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\QuoteController;
use Illuminate\Support\Facades\Route;

Route::get('/locations', [CatalogController::class, 'locations']);
Route::get('/vehicle-types', [CatalogController::class, 'vehicleTypes']);
Route::get('/vehicles', [CatalogController::class, 'vehicles']);
Route::get('/vehicles/{vehicle:slug}', [CatalogController::class, 'vehicle']);

Route::post('/quote', QuoteController::class);
Route::post('/bookings', [BookingController::class, 'store']);
