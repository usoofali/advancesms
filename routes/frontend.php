<?php

use App\Http\Controllers\FrontendAnnouncementController;
use App\Http\Controllers\FrontendContactController;
use App\Http\Controllers\FrontendController;
use Illuminate\Support\Facades\Route;

Route::get('/', [FrontendController::class, 'index'])->name('home');
Route::get('about', [FrontendController::class, 'about'])->name('frontend.about');
Route::get('academics', [FrontendController::class, 'academics'])->name('frontend.academics');
Route::get('gallery', [FrontendController::class, 'gallery'])->name('frontend.gallery');
Route::get('faq', [FrontendController::class, 'faq'])->name('frontend.faq');

Route::get('announcements', [FrontendAnnouncementController::class, 'index'])->name('frontend.announcements.index');
Route::get('announcements/{slug}', [FrontendAnnouncementController::class, 'show'])->name('frontend.announcements.show');

Route::get('contact', [FrontendContactController::class, 'index'])->name('frontend.contact.index');
Route::post('contact', [FrontendContactController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('frontend.contact.store');
