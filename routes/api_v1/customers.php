<?php

use App\Customer\Controllers\CustomerController;
use App\Customer\Controllers\ReviewController;
use Illuminate\Support\Facades\Route;

Route::apiResource('customers', CustomerController::class);
Route::get('reviews', [ReviewController::class, 'index']);
Route::post('reviews', [ReviewController::class, 'store']);
Route::put('reviews/{id}', [ReviewController::class, 'update']);
