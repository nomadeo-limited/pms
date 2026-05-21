<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['throttle:60,1'])->group(function () {
    require base_path('routes/api_v1/auth.php');
});

Route::prefix('v1')->middleware(['auth:api', 'resolve.tenant'])->group(function () {
    require base_path('routes/api_v1/organizers.php');
    require base_path('routes/api_v1/properties.php');
    require base_path('routes/api_v1/inventory.php');
    require base_path('routes/api_v1/programs.php');
    require base_path('routes/api_v1/availability.php');
    require base_path('routes/api_v1/pricing.php');
    require base_path('routes/api_v1/customers.php');
    require base_path('routes/api_v1/bookings.php');
    require base_path('routes/api_v1/payments.php');
    require base_path('routes/api_v1/staff.php');
    require base_path('routes/api_v1/reporting.php');
    require base_path('routes/api_v1/housekeeping.php');
    require base_path('routes/api_v1/front-desk.php');
    require base_path('routes/api_v1/tax-rates.php');
});

Route::prefix('v1/integration')->middleware(['integration.token', 'throttle:120,1'])->group(function () {
    require base_path('routes/api_v1/integration.php');
});

Route::prefix('v1')->middleware(['auth:api', 'resolve.tenant'])->group(function () {
    Route::post('integration/tokens', [\App\Integration\Controllers\NomadeoIntegrationController::class, 'createToken']);
});
