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
        require __DIR__ . '/api/stock_routes.php';
        require __DIR__ . '/api/product_stock__transfer_routes.php';
        require __DIR__ . '/api/stock_to_stock_routes.php';
        require __DIR__ . '/api/sell_routes.php';
        require __DIR__ . '/api/customer_routes.php';
        require __DIR__ . '/api/container_routes.php';
        require __DIR__ . '/api/receive_product_routes.php';
        require __DIR__ . '/api/expense_income_category_routes.php';
        require __DIR__ . '/api/product_back_routes.php';
        require __DIR__ . '/api/capital_routes.php';
        require __DIR__ . '/api/backup_routes.php';
        Route::get("analytics", [DashboardController::class, "index"]);
        Route::get("reports", [DashboardController::class, "reports"]);
        Route::get("get_report", [DashboardController::class, "getReports"]);
        Route::get("customer_reports", [DashboardController::class, "getCustomerReports"]);
        Route::get("get_profit_lost", [DashboardController::class, "getProfitLost"]);
    });
});
