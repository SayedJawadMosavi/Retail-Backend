<?php

use App\Http\Controllers\ProductCategoryController;
use Illuminate\Support\Facades\Route;

Route::apiResource('product-category', ProductCategoryController::class);
Route::post('restore-product-category/{id}', [ProductCategoryController::class, 'restore']);
Route::post('product-category-status/{value}/{id}', [ProductCategoryController::class, 'changeStatus']);
Route::delete('force-delete-product-category/{id}', [ProductCategoryController::class, 'forceDelete']);


