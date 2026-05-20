<?php

use App\Program\Controllers\AddOnController;
use App\Program\Controllers\ProgramController;
use Illuminate\Support\Facades\Route;

Route::apiResource('programs', ProgramController::class);
Route::apiResource('add-ons', AddOnController::class);
Route::post('programs/{programId}/add-ons/{addOnId}', [AddOnController::class, 'attach']);
Route::delete('programs/{programId}/add-ons/{addOnId}', [AddOnController::class, 'detach']);
