<?php

use App\Tax\Controllers\TaxRateController;
use Illuminate\Support\Facades\Route;

Route::get('tax-rates', [TaxRateController::class, 'index']);
Route::post('tax-rates', [TaxRateController::class, 'store']);
Route::put('tax-rates/{id}', [TaxRateController::class, 'update']);
Route::delete('tax-rates/{id}', [TaxRateController::class, 'destroy']);
