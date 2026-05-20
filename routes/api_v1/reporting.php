<?php

use App\Reporting\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::get('reports/occupancy', [ReportController::class, 'occupancy']);
Route::get('reports/revenue', [ReportController::class, 'revenue']);
Route::get('reports/bookings', [ReportController::class, 'bookingStats']);
Route::get('reports/customers', [ReportController::class, 'customerStats']);
