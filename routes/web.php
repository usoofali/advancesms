<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\RolePermissionController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/frontend.php';

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('roles', [RolePermissionController::class, 'index'])->name('roles.index');
        Route::post('roles', [RolePermissionController::class, 'store'])->name('roles.store');
        Route::put('roles/{role}', [RolePermissionController::class, 'update'])->name('roles.update');
        Route::delete('roles/{role}', [RolePermissionController::class, 'destroy'])->name('roles.destroy');

        Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
        Route::delete('activity-logs/batch', [ActivityLogController::class, 'destroyBatch'])->name('activity-logs.destroy-batch');
        Route::post('activity-logs/clear', [ActivityLogController::class, 'clearAll'])->name('activity-logs.clear');
        Route::delete('activity-logs/{activityLog}', [ActivityLogController::class, 'destroy'])->name('activity-logs.destroy');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/academic.php';
require __DIR__.'/people.php';
