<?php

use App\Http\Controllers\ProductBackController;
use Illuminate\Support\Facades\Route;

Route::apiResource('product_back', ProductBackController::class);
Route::post('restore-product_back/{id}', [ProductBackController::class, 'restore']);
Route::delete('force-delete-product_back/{id}', [ProductBackController::class, 'forceDelete']);

Route::get('sell-product-list', [ProductBackController::class, 'getSellProductList']);
Route::get('item-list/{id}', [ProductBackController::class, 'getSellItem']);
Route::get('item-price/{id}', [ProductBackController::class, 'getSellItempPrice']);




