<?php

use App\Inventory\Controllers\RoomController;
use App\Inventory\Controllers\RoomTypeController;
use App\Inventory\Controllers\UnitBlockController;
use App\Inventory\Controllers\UnitController;
use Illuminate\Support\Facades\Route;

Route::apiResource('room-types', RoomTypeController::class);
Route::apiResource('rooms', RoomController::class);
Route::get('units/availability', [UnitController::class, 'availability']);
Route::apiResource('units', UnitController::class);
Route::apiResource('unit-blocks', UnitBlockController::class)->except(['show']);
