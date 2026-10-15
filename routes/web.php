<?php

use App\Http\Controllers\RolePermissionController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('roles', [RolePermissionController::class, 'index'])->name('roles.index');
        Route::post('roles', [RolePermissionController::class, 'store'])->name('roles.store');
        Route::put('roles/{role}', [RolePermissionController::class, 'update'])->name('roles.update');
        Route::delete('roles/{role}', [RolePermissionController::class, 'destroy'])->name('roles.destroy');
    });
});

require __DIR__.'/settings.php';
