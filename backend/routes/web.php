<?php

use App\Http\Controllers\QrCodeController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/qr/coupon/{code}.svg', [QrCodeController::class, 'coupon'])->name('qr.coupon');
