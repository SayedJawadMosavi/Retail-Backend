<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::apiResource('/users', UserController::class);
Route::post('/logout', [AuthController::class, 'logout']);
Route::put('/edit-users', [UserController::class, 'editUser']);
Route::post('restore-users/{id}', [UserController::class, 'restore']);
Route::delete('users-force-delete/{id}', [UserController::class, 'forceDelete']);
Route::post('change-password', [UserController::class, 'changePassword']);
