<?php

use App\Payment\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

Route::get('bookings/{bookingId}/payments', [PaymentController::class, 'index']);
Route::post('bookings/{bookingId}/payments', [PaymentController::class, 'store']);
Route::get('payment-rules', [PaymentController::class, 'indexRules']);
Route::post('payment-rules', [PaymentController::class, 'storeRule']);
