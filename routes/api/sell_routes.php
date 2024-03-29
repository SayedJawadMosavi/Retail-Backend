<?php

use App\Http\Controllers\SellController;
use Illuminate\Support\Facades\Route;

Route::apiResource('sell', SellController::class);
Route::post('/sell-item', [SellController::class, 'addItem']);
Route::put('/sell-item', [SellController::class, 'updateItem']);
Route::post('/sell-payment', [SellController::class, 'addPayment']);
Route::put('/sell-payment', [SellController::class, 'updatePayment']);
Route::post('/restore/{type}/{id}', [SellController::class, 'restore']);
Route::delete('/delete/{type}/{id}', [SellController::class, 'destroy']);
Route::delete('/force-delete/{type}/{id}', [SellController::class, 'forceDelete']);
Route::get('customer-list', [SellController::class, 'getCustomer']);
Route::get('product-stock-list', [SellController::class, 'getProductStock']);
Route::get('get-product-stock-list/{id}', [SellController::class, 'getProduct']);
