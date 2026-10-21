<?php

use App\Http\Controllers\SchoolSettingController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/Appearance')->name('appearance.edit');

    Route::get('settings/school', [SchoolSettingController::class, 'edit'])->name('school-settings.edit');
    Route::post('settings/school', [SchoolSettingController::class, 'update'])->name('school-settings.update');

    // Consolidated CMS management routes
    Route::prefix('settings/school')->name('school-settings.')->group(function () {
        // Hero Sliders
        Route::post('sliders', [SchoolSettingController::class, 'storeSlider'])->name('sliders.store');
        Route::post('sliders/{slider}', [SchoolSettingController::class, 'updateSlider'])->name('sliders.update');
        Route::delete('sliders/{slider}', [SchoolSettingController::class, 'destroySlider'])->name('sliders.destroy');

        // Announcements
        Route::post('announcements', [SchoolSettingController::class, 'storeAnnouncement'])->name('announcements.store');
        Route::post('announcements/{announcement}', [SchoolSettingController::class, 'updateAnnouncement'])->name('announcements.update');
        Route::delete('announcements/{announcement}', [SchoolSettingController::class, 'destroyAnnouncement'])->name('announcements.destroy');

        // Campus Gallery
        Route::post('galleries', [SchoolSettingController::class, 'storeGallery'])->name('galleries.store');
        Route::delete('galleries/{gallery}', [SchoolSettingController::class, 'destroyGallery'])->name('galleries.destroy');

        // FAQs
        Route::post('faqs', [SchoolSettingController::class, 'storeFaq'])->name('faqs.store');
        Route::put('faqs/{faq}', [SchoolSettingController::class, 'updateFaq'])->name('faqs.update');
        Route::delete('faqs/{faq}', [SchoolSettingController::class, 'destroyFaq'])->name('faqs.destroy');

        // Inquiries
        Route::patch('inquiries/{inquiry}', [SchoolSettingController::class, 'updateInquiry'])->name('inquiries.update');
        Route::delete('inquiries/{inquiry}', [SchoolSettingController::class, 'destroyInquiry'])->name('inquiries.destroy');

        // Grading Scale
        Route::post('grades', [SchoolSettingController::class, 'storeGrade'])->name('grades.store');
        Route::put('grades/{grade}', [SchoolSettingController::class, 'updateGrade'])->name('grades.update');
        Route::delete('grades/{grade}', [SchoolSettingController::class, 'destroyGrade'])->name('grades.destroy');
    });
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
