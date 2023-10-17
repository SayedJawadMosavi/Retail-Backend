<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::group(['prefix' => 'v1'], function () {
    // user login route
    Route::post("login", [AuthController::class, "login"]);
    Route::group(['middleware' => 'auth:sanctum'], function () {
        require __DIR__ . '/api/user_routes.php';
        require __DIR__ . '/api/vendor_routes.php';
        require __DIR__ . '/api/product_routes.php';
        require __DIR__ . '/api/product_category_routes.php';
        require __DIR__ . '/api/incoming_outgoing_routes.php';
        require __DIR__ . '/api/employee_routes.php';
        require __DIR__ . '/api/treasury_log_routes.php';
        require __DIR__ . '/api/purchase_routes.php';
        Route::get("analytics", [DashboardController::class, "index"]);
        Route::get("reports", [DashboardController::class, "reports"]);
    });
});
