<?php
use App\Housekeeping\Controllers\HousekeepingController;
use Illuminate\Support\Facades\Route;

Route::get('housekeeping', [HousekeepingController::class, 'index']);
Route::put('housekeeping', [HousekeepingController::class, 'upsert']);
