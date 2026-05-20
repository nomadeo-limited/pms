<?php

use App\Integration\Controllers\NomadeoIntegrationController;
use Illuminate\Support\Facades\Route;

Route::get('{propertySlug}/availability', [NomadeoIntegrationController::class, 'availability']);
Route::post('{propertySlug}/bookings', [NomadeoIntegrationController::class, 'createBooking']);
Route::post('{propertySlug}/customers', [NomadeoIntegrationController::class, 'upsertCustomer']);
