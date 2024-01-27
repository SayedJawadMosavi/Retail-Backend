<?php
use App\Http\Controllers\ReceiveProductController;
use Illuminate\Support\Facades\Route;

Route::apiResource('receive_product', ReceiveProductController::class);
