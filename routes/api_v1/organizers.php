<?php

use App\Organizer\Controllers\OrganizerController;
use Illuminate\Support\Facades\Route;

Route::apiResource('organizers', OrganizerController::class);
