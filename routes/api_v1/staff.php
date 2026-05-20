<?php

use App\Staff\Controllers\StaffController;
use Illuminate\Support\Facades\Route;

Route::get('staff', [StaffController::class, 'index']);
Route::post('staff/invite', [StaffController::class, 'invite']);
Route::put('staff/{userId}/role', [StaffController::class, 'updateRole']);
Route::delete('staff/{userId}', [StaffController::class, 'destroy']);
