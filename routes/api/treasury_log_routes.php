<?php

use App\Http\Controllers\TreasuryLogController;
use Illuminate\Support\Facades\Route;

Route::apiResource('/treasury-log', TreasuryLogController::class);

Route::get("treasury_log_reports", [TreasuryLogController::class, "reports"]);

