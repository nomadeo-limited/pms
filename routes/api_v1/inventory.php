<?php

use App\Inventory\Controllers\RoomTypeController;
use App\Inventory\Controllers\UnitController;
use Illuminate\Support\Facades\Route;

Route::apiResource('room-types', RoomTypeController::class);
Route::get('units/availability', [UnitController::class, 'availability']);
Route::apiResource('units', UnitController::class);
