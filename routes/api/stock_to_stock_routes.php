<?php

use App\Http\Controllers\StockToStockTransferController;
use Illuminate\Support\Facades\Route;

Route::apiResource('stock_to_stocks_transfer', StockToStockTransferController::class);
Route::post('restore-stock_to_stocks_transfer/{id}', [StockToStockTransferController::class, 'restore']);
Route::delete('force-delete-stock_to_stocks_transfer/{id}', [StockToStockTransferController::class, 'forceDelete']);

Route::get('product-list/{id}', [StockToStockTransferController::class, 'getProduct']);

Route::get('stock-list/{id}', [StockToStockTransferController::class, 'getStock']);



