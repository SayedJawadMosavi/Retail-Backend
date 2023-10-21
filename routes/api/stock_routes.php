<?php

use App\Http\Controllers\StockController;
use Illuminate\Support\Facades\Route;

Route::apiResource('stock', StockController::class);
Route::post('restore-stock/{id}', [StockController::class, 'restore']);
Route::post('stock-status', [StockController::class, 'changeStatus']);
Route::delete('force-delete-stock/{id}', [StockController::class, 'forceDelete']);


