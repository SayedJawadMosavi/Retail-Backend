<?php

use App\Http\Controllers\ContainerController;
use Illuminate\Support\Facades\Route;

Route::apiResource('container', ContainerController::class);
Route::post('restore-container/{id}', [ContainerController::class, 'restore']);
Route::post('container-status/{value}/{id}', [ContainerController::class, 'changeStatus']);
Route::delete('force-delete-container/{id}', [ContainerController::class, 'forceDelete']);


