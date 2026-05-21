<?php
use App\FrontDesk\Controllers\FrontDeskController;
use Illuminate\Support\Facades\Route;

Route::get('front-desk', [FrontDeskController::class, 'show']);
