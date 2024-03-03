<?php

use App\Http\Controllers\CapitalController;
use Illuminate\Support\Facades\Route;

Route::apiResource('capital', CapitalController::class);
Route::post('restore-capital/{id}', [CapitalController::class, 'restore']);
Route::post('capital-status', [CapitalController::class, 'changeStatus']);
Route::delete('force-delete-capital/{id}', [CapitalController::class, 'forceDelete']);


