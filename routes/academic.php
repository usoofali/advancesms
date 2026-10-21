<?php

use App\Http\Controllers\AcademicSessionController;
use App\Http\Controllers\AcademicTermController;
use App\Http\Controllers\AttendanceReportController;
use App\Http\Controllers\BroadsheetController;
use App\Http\Controllers\ClassSubjectController;
use App\Http\Controllers\DailyAttendanceController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\ReportCardController;
use App\Http\Controllers\SchoolClassController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\StudentMarkController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\TeacherSubjectAssignmentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('academic')->name('academic.')->group(function () {
    // Academic Sessions
    Route::get('sessions', [AcademicSessionController::class, 'index'])->name('sessions.index');
    Route::post('sessions', [AcademicSessionController::class, 'store'])->name('sessions.store');
    Route::put('sessions/{session}', [AcademicSessionController::class, 'update'])->name('sessions.update');
    Route::delete('sessions/{session}', [AcademicSessionController::class, 'destroy'])->name('sessions.destroy');
    Route::post('sessions/{session}/set-current', [AcademicSessionController::class, 'setCurrent'])->name('sessions.set-current');

    // Academic Terms
    Route::post('terms', [AcademicTermController::class, 'store'])->name('terms.store');
    Route::put('terms/{term}', [AcademicTermController::class, 'update'])->name('terms.update');
    Route::delete('terms/{term}', [AcademicTermController::class, 'destroy'])->name('terms.destroy');
    Route::post('terms/{term}/set-current', [AcademicTermController::class, 'setCurrent'])->name('terms.set-current');

    // Classes & Sections
    Route::get('classes', [SchoolClassController::class, 'index'])->name('classes.index');
    Route::post('classes', [SchoolClassController::class, 'store'])->name('classes.store');
    Route::put('classes/{class}', [SchoolClassController::class, 'update'])->name('classes.update');
    Route::delete('classes/{class}', [SchoolClassController::class, 'destroy'])->name('classes.destroy');

    // Class Sections
    Route::post('sections', [SectionController::class, 'store'])->name('sections.store');
    Route::put('sections/{section}', [SectionController::class, 'update'])->name('sections.update');
    Route::delete('sections/{section}', [SectionController::class, 'destroy'])->name('sections.destroy');

    // Academic Departments
    Route::get('departments', [DepartmentController::class, 'index'])->name('departments.index');
    Route::post('departments', [DepartmentController::class, 'store'])->name('departments.store');
    Route::put('departments/{department}', [DepartmentController::class, 'update'])->name('departments.update');
    Route::delete('departments/{department}', [DepartmentController::class, 'destroy'])->name('departments.destroy');

    // Master Subjects Catalog
    Route::get('subjects', [SubjectController::class, 'index'])->name('subjects.index');
    Route::post('subjects', [SubjectController::class, 'store'])->name('subjects.store');
    Route::put('subjects/{subject}', [SubjectController::class, 'update'])->name('subjects.update');
    Route::delete('subjects/{subject}', [SubjectController::class, 'destroy'])->name('subjects.destroy');

    // Class Curriculum Allocation
    Route::post('class-subjects', [ClassSubjectController::class, 'store'])->name('class-subjects.store');
    Route::put('class-subjects/{classSubject}', [ClassSubjectController::class, 'update'])->name('class-subjects.update');
    Route::delete('class-subjects/{classSubject}', [ClassSubjectController::class, 'destroy'])->name('class-subjects.destroy');
    Route::post('class-subjects/bulk-assign', [ClassSubjectController::class, 'bulkAssign'])->name('class-subjects.bulk-assign');

    // Student Enrollment Engine
    Route::get('enrollments', [EnrollmentController::class, 'index'])->name('enrollments.index');
    Route::post('enrollments', [EnrollmentController::class, 'store'])->name('enrollments.store');
    Route::put('enrollments/{enrollment}', [EnrollmentController::class, 'update'])->name('enrollments.update');
    Route::delete('enrollments/{enrollment}', [EnrollmentController::class, 'destroy'])->name('enrollments.destroy');
    Route::post('enrollments/promote', [PromotionController::class, 'store'])->name('enrollments.promote');

    // Student Promotion & Progression Engine
    Route::get('promotions', [PromotionController::class, 'index'])->name('promotions.index');
    Route::post('promotions', [PromotionController::class, 'store'])->name('promotions.store');

    // Teacher Subject Allocations
    Route::get('allocations', [TeacherSubjectAssignmentController::class, 'index'])->name('allocations.index');
    Route::post('allocations', [TeacherSubjectAssignmentController::class, 'store'])->name('allocations.store');
    Route::put('allocations/{assignment}', [TeacherSubjectAssignmentController::class, 'update'])->name('allocations.update');
    Route::delete('allocations/{assignment}', [TeacherSubjectAssignmentController::class, 'destroy'])->name('allocations.destroy');

    // Continuous Assessment & Exam Mark Entry Grid
    Route::get('marks', [StudentMarkController::class, 'index'])->name('marks.index');
    Route::post('marks/batch-save', [StudentMarkController::class, 'batchSave'])->name('marks.batch-save');

    // Daily Attendance Register
    Route::get('attendance', [DailyAttendanceController::class, 'index'])->name('attendance.index');
    Route::post('attendance', [DailyAttendanceController::class, 'store'])->name('attendance.store');

    // Result Broadsheet Engine
    Route::get('broadsheet', [BroadsheetController::class, 'index'])->name('broadsheet.index');

    // Attendance Reports & Analytics
    Route::get('reports/attendance', [AttendanceReportController::class, 'index'])->name('reports.attendance');

    // Terminal Report Cards
    Route::get('report-cards', [ReportCardController::class, 'index'])->name('report-cards.index');
    Route::get('report-cards/batch-data', [ReportCardController::class, 'batchData'])->name('report-cards.batch-data');
    Route::get('report-cards/{enrollment}', [ReportCardController::class, 'show'])->name('report-cards.show');
});
