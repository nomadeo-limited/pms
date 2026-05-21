<?php

use App\Booking\Controllers\BookingController;
use Illuminate\Support\Facades\Route;

Route::get('bookings/calendar', [BookingController::class, 'calendar']);
Route::get('bookings/{id}/invoice', [BookingController::class, 'invoice']);
Route::apiResource('bookings', BookingController::class)->except(['destroy']);
