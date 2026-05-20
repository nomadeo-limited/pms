<?php

use App\Organizer\Controllers\PropertyController;
use Illuminate\Support\Facades\Route;

Route::apiResource('properties', PropertyController::class);
