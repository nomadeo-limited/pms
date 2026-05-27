<?php

use App\Staff\Controllers\StaffController;
use Illuminate\Support\Facades\Route;

Route::get('staff', [StaffController::class, 'index']);
Route::post('staff/invite', [StaffController::class, 'invite'])->middleware('staff.role.authorize');
Route::put('staff/{userId}/role', [StaffController::class, 'updateRole'])->middleware('staff.role.authorize');
Route::put('staff/{userId}/password', [StaffController::class, 'updatePassword'])->middleware('staff.role.authorize');
Route::delete('staff/{userId}', [StaffController::class, 'destroy'])->middleware('staff.role.authorize');
