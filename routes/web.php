<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\VisitorController;
use App\Http\Controllers\VisitorCheckinController;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminVisitorController;


Route::get('/', function () {
    return view('welcome');
});
Route::get('/visitor/create', [VisitorController::class, 'create'])->name('visitor.create');
Route::get('/visitor/upload-document', [VisitorController::class, 'showUploadDocument'])->name('visitor.upload_document');
Route::get('/visitor/live-face-check', [VisitorController::class, 'showLiveFaceCheck'])->name('visitor.live_face');
Route::get('/visitor/session-photo/{type?}', [VisitorController::class, 'sessionPhoto'])->name('visitor.session_photo');
Route::post('/visitor/confirm', [VisitorController::class, 'confirm'])->name('visitor.confirm');
Route::post('/visitor/payment-method', [VisitorController::class, 'selectPaymentMethod'])->name('visitor.payment-method');
Route::get('/visitor/payment/card', [VisitorController::class, 'cardGateway'])->name('visitor.payment.card');
Route::get('/visitor/payment/cash', [VisitorController::class, 'cashConfirmation'])->name('visitor.payment.cash');
Route::post('/visitor/payment/confirm', [VisitorController::class, 'confirmPayment'])->name('visitor.payment.confirm');
Route::get('/visitor/thank-you', [VisitorController::class, 'thankYou'])->name('visitor.thank-you');
Route::get('/visitor/list', fn () => redirect()->route('admin.visitors.index'))->name('visitor.list');
Route::post('/visitor', [VisitorController::class, 'store'])->name('visitor.store');
Route::delete('/visitor/{visitorId}', [VisitorController::class, 'checkout'])->name('visitor.checkout');

Route::post('/api/visitor/verify-vision', [VisitorCheckinController::class, 'verifyVision'])->name('visitor.verify_vision');
Route::post('/api/visitor/verify-live-face', [VisitorCheckinController::class, 'verifyLiveFace'])->middleware('throttle:10,1')->name('visitor.verify_live_face');
Route::post('/api/visitor/verify-session', [VisitorCheckinController::class, 'verifyVision'])->name('visitor.session');

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [AdminAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AdminAuthController::class, 'login'])->middleware('throttle:5,1')->name('login.submit');

    Route::middleware('admin.auth')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::get('/visitors', [AdminVisitorController::class, 'index'])->name('visitors.index');
        Route::patch('/visitors/{visitor}/checkin', [AdminVisitorController::class, 'toggleCheckin'])->name('visitors.checkin');
        Route::patch('/visitors/{visitor}', [AdminVisitorController::class, 'update'])->name('visitors.update');
        Route::delete('/visitors/{visitor}', [AdminVisitorController::class, 'destroy'])->name('visitors.destroy');
        Route::get('/visitors/{visitor}/photo', [AdminVisitorController::class, 'photo'])->name('visitors.photo');
        Route::get('/visitors/{visitor}/back-photo', [AdminVisitorController::class, 'backPhoto'])->name('visitors.back_photo');
        Route::get('/visitors/{visitor}/selfie', [AdminVisitorController::class, 'selfie'])->name('visitors.selfie');
        Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');
    });
});
