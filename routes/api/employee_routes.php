<?php

use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\SalaryPaymentController;
use Illuminate\Support\Facades\Route;

Route::apiResource('employees', EmployeeController::class);

Route::post('restore-employees/{id}', [EmployeeController::class, 'restore']);
Route::delete('force-delete-employees/{id}', [EmployeeController::class, 'forceDelete']);
Route::get('employee-list', [EmployeeController::class, 'getEmployees']);
Route::apiResource('salary-payments', SalaryPaymentController::class);
Route::post('restore/salary-payments/{id}', [SalaryPaymentController::class, 'restore']);
Route::delete('force-delete-salary-payments/{id}', [SalaryPaymentController::class, 'forceDelete']);
Route::post('/employee_loan', [EmployeeController::class, 'addILoan']);
Route::put('/employee_loan', [EmployeeController::class, 'updateLoan']);
Route::post('employee_restore/{type}/{id}', [EmployeeController::class, 'restore']);
Route::delete('employee_delete/{type}/{id}', [EmployeeController::class, 'destroy']);
Route::delete('employee_force_delete/{type}/{id}', [EmployeeController::class, 'forceDelete']);
Route::get('get-data', [EmployeeController::class, 'getData']);
Route::get("employee_reports", [EmployeeController::class, "reports"]);