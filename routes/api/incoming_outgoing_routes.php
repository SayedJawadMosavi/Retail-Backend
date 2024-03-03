<?php

use App\Http\Controllers\IncomingOutgoingController;
use App\Http\Controllers\ExpenseIncomeCategoryController;
use Illuminate\Support\Facades\Route;

Route::apiResource('/income-outgoing', IncomingOutgoingController::class);
Route::post('/restore-incoming-outgoing/{id}', [IncomingOutgoingController::class, 'restore']);
Route::delete('/force-delete-income-outgoing/{id}', [IncomingOutgoingController::class, 'forceDelete']);
Route::get('expense-income-category-list', [ExpenseIncomeCategoryController::class, 'getCategory']);
