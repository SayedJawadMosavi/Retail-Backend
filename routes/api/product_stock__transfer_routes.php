<?php

use App\Http\Controllers\ProductStockController;
use Illuminate\Support\Facades\Route;

Route::apiResource('product_stocks_transfer', ProductStockController::class);
Route::post('restore-product_stocks_transfer/{id}', [ProductStockController::class, 'restore']);
Route::delete('force-delete-product_stocks_transfer/{id}', [ProductStockController::class, 'forceDelete']);

Route::get('product-list', [ProductStockController::class, 'getProduct']);
Route::get('stock-list', [ProductStockController::class, 'getStock']);
Route::get('product_stocks', [ProductStockController::class, 'getStockProduct']);



