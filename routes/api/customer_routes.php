<?php

use App\Http\Controllers\CustomerController;
use Illuminate\Support\Facades\Route;

Route::apiResource('customer', CustomerController::class);
Route::post('restore-customer/{id}', [CustomerController::class, 'restore']);
Route::post('customer-status/{value}/{id}', [CustomerController::class, 'changeStatus']);
Route::delete('force-delete-customer/{id}', [CustomerController::class, 'forceDelete']);
Route::get("customer_reports", [CustomerController::class, "reports"]);



