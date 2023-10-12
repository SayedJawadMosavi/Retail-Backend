<?php

use App\Http\Controllers\VendorController;
use Illuminate\Support\Facades\Route;

Route::apiResource('vendor', VendorController::class);
Route::post('restore-vendor/{id}', [VendorController::class, 'restore']);
Route::post('vendor-status', [VendorController::class, 'changeStatus']);
Route::delete('force-delete-vendor/{id}', [VendorController::class, 'forceDelete']);



