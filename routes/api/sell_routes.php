<?php

use App\Http\Controllers\sellController;
use Illuminate\Support\Facades\Route;

Route::apiResource('sell', sellController::class);
Route::post('/sell-item', [sellController::class, 'addItem']);
Route::put('/sell-item', [sellController::class, 'updateItem']);
Route::post('/sell-payment', [sellController::class, 'addPayment']);
Route::put('/sell-payment', [sellController::class, 'updatePayment']);
Route::post('/restore/{type}/{id}', [sellController::class, 'restore']);
Route::delete('/delete/{type}/{id}', [sellController::class, 'destroy']);
Route::delete('/force-delete/{type}/{id}', [sellController::class, 'forceDelete']);
Route::get('customer-list', [sellController::class, 'getCustomer']);
Route::get('product-stock-list', [sellController::class, 'getProductStock']);
