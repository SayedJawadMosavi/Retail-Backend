<?php

use App\Http\Controllers\ExpenseIncomeCategoryController;
use Illuminate\Support\Facades\Route;

Route::apiResource('expense-income-category', ExpenseIncomeCategoryController::class);
Route::post('restore-expense-income-category/{id}', [ExpenseIncomeCategoryController::class, 'restore']);
Route::post('expense-income-category-status/{value}/{id}', [ExpenseIncomeCategoryController::class, 'changeStatus']);
Route::delete('force-delete-expense-income-category/{id}', [ExpenseIncomeCategoryController::class, 'forceDelete']);


