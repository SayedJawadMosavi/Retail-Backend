<?php
use App\Http\Controllers\BackupController;
use Illuminate\Support\Facades\Route;
Route::apiResource('backups', BackupController::class);
Route::delete('force-delete-backup/{id}', [BackupController::class, 'forceDelete']);
Route::get('/download-backup/{file_name}', [BackupController::class, 'show'])->name('download.backup');



