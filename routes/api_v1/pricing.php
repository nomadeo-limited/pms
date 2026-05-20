<?php

use App\Pricing\Controllers\PricingController;
use Illuminate\Support\Facades\Route;

Route::get('pricing/calculate', [PricingController::class, 'calculate']);
Route::get('pricing-rules', [PricingController::class, 'indexRules']);
Route::post('pricing-rules', [PricingController::class, 'storeRule']);
Route::put('pricing-rules/{id}', [PricingController::class, 'updateRule']);
Route::delete('pricing-rules/{id}', [PricingController::class, 'destroyRule']);
Route::get('discounts', [PricingController::class, 'indexDiscounts']);
Route::post('discounts', [PricingController::class, 'storeDiscount']);
Route::delete('discounts/{id}', [PricingController::class, 'destroyDiscount']);
