<?php

use App\Http\Controllers\AlumniController;
use App\Http\Controllers\GuardianController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\TeacherAttendanceController;
use App\Http\Controllers\TeacherAttendanceReportController;
use App\Http\Controllers\TeacherController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('people')->name('people.')->group(function () {
    // Teachers & Staff Directory
    Route::get('teachers', [TeacherController::class, 'index'])->name('teachers.index');
    Route::post('teachers', [TeacherController::class, 'store'])->name('teachers.store');
    Route::put('teachers/{teacher}', [TeacherController::class, 'update'])->name('teachers.update');
    Route::put('teachers/{teacher}/reset-password', [TeacherController::class, 'resetPassword'])->name('teachers.reset-password');
    Route::delete('teachers/{teacher}', [TeacherController::class, 'destroy'])->name('teachers.destroy');

    // Teacher Attendance Register & Reports
    Route::get('teachers/attendance', [TeacherAttendanceController::class, 'index'])->name('teachers.attendance.index');
    Route::post('teachers/attendance', [TeacherAttendanceController::class, 'store'])->name('teachers.attendance.store');
    Route::delete('teachers/attendance/{date}', [TeacherAttendanceController::class, 'destroy'])->name('teachers.attendance.destroy');
    Route::get('teachers/attendance/report', [TeacherAttendanceReportController::class, 'index'])->name('teachers.attendance.report');

    // Students Directory
    Route::get('students', [StudentController::class, 'index'])->name('students.index');
    Route::post('students', [StudentController::class, 'store'])->name('students.store');
    Route::get('students/{student}', [StudentController::class, 'show'])->name('students.show');
    Route::put('students/{student}', [StudentController::class, 'update'])->name('students.update');
    Route::delete('students/{student}', [StudentController::class, 'destroy'])->name('students.destroy');

    // Alumni & Graduated Students Directory
    Route::get('alumni', [AlumniController::class, 'index'])->name('alumni.index');

    // Guardians Directory
    Route::get('guardians', [GuardianController::class, 'index'])->name('guardians.index');
    Route::post('guardians', [GuardianController::class, 'store'])->name('guardians.store');
    Route::put('guardians/{guardian}', [GuardianController::class, 'update'])->name('guardians.update');
    Route::delete('guardians/{guardian}', [GuardianController::class, 'destroy'])->name('guardians.destroy');
});
