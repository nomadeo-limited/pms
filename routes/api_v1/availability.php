<?php

use App\Availability\Controllers\AvailabilityController;
use Illuminate\Support\Facades\Route;

Route::get('availability', [AvailabilityController::class, 'calendar']);
Route::get('availability/check', [AvailabilityController::class, 'check']);
Route::get('availability/rules', [AvailabilityController::class, 'indexRules']);
Route::post('availability/rules', [AvailabilityController::class, 'storeRule']);
Route::delete('availability/rules/{id}', [AvailabilityController::class, 'destroyRule']);
Route::get('booking-rules', [AvailabilityController::class, 'indexBookingRules']);
Route::post('booking-rules', [AvailabilityController::class, 'storeBookingRule']);
