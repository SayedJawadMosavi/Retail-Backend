<?php

use App\Http\Controllers\VendorController;
use Illuminate\Support\Facades\Route;

Route::apiResource('vendor', VendorController::class);
Route::post('restore-vendor/{id}', [VendorController::class, 'restore']);

Route::delete('force-delete-vendor/{id}', [VendorController::class, 'forceDelete']);
Route::get('vendor-purchase', [VendorController::class, 'vendorPurchase']);
Route::post('vendor-status/{value}/{id}', [VendorController::class, 'changeStatus']);





