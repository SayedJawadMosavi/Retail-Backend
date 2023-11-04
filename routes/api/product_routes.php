<?php

use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::apiResource('product', ProductController::class);
Route::post('restore-product/{id}', [ProductController::class, 'restore']);
Route::delete('force-delete-product/{id}', [ProductController::class, 'forceDelete']);

Route::get('category-list', [ProductController::class, 'getCategory']);
Route::post('product-status/{value}/{id}', [ProductController::class, 'changeStatus']);



