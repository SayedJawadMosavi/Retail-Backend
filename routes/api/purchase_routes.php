<?php

use App\Http\Controllers\PurchaseController;
use Illuminate\Support\Facades\Route;

Route::apiResource('purchase', PurchaseController::class);
Route::post('/purchase-item', [PurchaseController::class, 'addItem']);
Route::put('/purchase-item', [PurchaseController::class, 'updateItem']);
Route::post('/purchase-expense', [PurchaseController::class, 'addExpense']);
Route::put('/purchase-expense', [PurchaseController::class, 'updateExpense']);
Route::post('/purchase-payment', [PurchaseController::class, 'addPayment']);
Route::put('/purchase-payment', [PurchaseController::class, 'updatePayment']);
Route::post('/restore_purchase/{type}/{id}', [PurchaseController::class, 'restore']);
Route::delete('/delete_purchase/{type}/{id}', [PurchaseController::class, 'destroy']);
Route::delete('/force-delete_purchase/{type}/{id}', [PurchaseController::class, 'forceDelete']);
Route::get('container-list', [PurchaseController::class, 'getContainer']);
Route::get('checkStatus/{id}', [PurchaseController::class, 'checkStatus']);
Route::get('vendor-list', [PurchaseController::class, 'getVendor']);
Route::get('product-list', [PurchaseController::class, 'getProduct']);



