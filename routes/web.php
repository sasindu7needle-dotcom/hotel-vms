<?php

use Illuminate\Support\Facades\Route;
use App\Models\VerifiedVisitor;
use App\Http\Controllers\VisitorController;
use App\Http\Controllers\VisitorCheckinController;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminCapacityController;
use App\Http\Controllers\AdminAttendanceController;
use App\Http\Controllers\AdminRevenueController;
use App\Http\Controllers\AdminVisitorController;
use App\Http\Controllers\AdminReceiptController;
use App\Http\Controllers\GateTerminalController;
use App\Http\Controllers\AdminEventConfigurationController;
use App\Http\Controllers\AdminEventRegistrationDayController;
use App\Http\Controllers\AdminVisitorCategoryController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AdminExhibitorController;
use App\Http\Controllers\AdminDailyReportScheduleController;
use App\Http\Controllers\ExhibitorRegistrationController;
use App\Http\Controllers\SuperAdminAuthController;
use App\Http\Controllers\SuperAdminDashboardController;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/visitor/new', [VisitorController::class, 'startNew'])->name('visitor.start');
Route::get('/visitor/registration-days', [VisitorController::class, 'registrationDays'])->name('visitor.registration-days');
Route::post('/visitor/registration-days/select', [VisitorController::class, 'selectRegistrationDay'])->name('visitor.registration-days.select');
Route::get('/visitor/manual-registration', [VisitorController::class, 'manualCreate'])->name('visitor.manual.create');
Route::post('/visitor/manual-registration/verify-identity', [VisitorController::class, 'manualVerifyIdentity'])->middleware('throttle:5,1')->name('visitor.manual.verify-identity');
Route::post('/visitor/manual-registration', [VisitorController::class, 'manualStore'])->name('visitor.manual.store');
Route::get('/visitor/create', [VisitorController::class, 'create'])->name('visitor.create');
Route::get('/visitor/upload-document', [VisitorController::class, 'showUploadDocument'])->name('visitor.upload_document');
Route::get('/visitor/photo-capture', [VisitorController::class, 'showPhotoCapture'])->name('visitor.photo_capture');
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

Route::prefix('exhibitor')->name('exhibitor.')->group(function () {
    Route::get('/register/{exhibitor}', [ExhibitorRegistrationController::class, 'show'])->name('registration.show');
    Route::post('/register/{exhibitor}/login', [ExhibitorRegistrationController::class, 'authenticate'])->middleware('throttle:5,1')->name('login');
    Route::post('/register/{exhibitor}', [ExhibitorRegistrationController::class, 'store'])->name('registration.store');
    Route::get('/register/{exhibitor}/members', [ExhibitorRegistrationController::class, 'dashboard'])->name('dashboard');
});

Route::post('/api/visitor/verify-vision', [VisitorCheckinController::class, 'verifyVision'])->name('visitor.verify_vision');
Route::post('/api/visitor/capture-photo', [VisitorCheckinController::class, 'capturePhoto'])->middleware('throttle:10,1')->name('visitor.capture_photo');
Route::post('/api/visitor/verify-session', [VisitorCheckinController::class, 'verifyVision'])->name('visitor.session');

Route::prefix('gate')->name('gate.')->group(function () {
    Route::get('/A/{direction}', [GateTerminalController::class, 'show'])->name('show');
    Route::post('/A/{direction}', [GateTerminalController::class, 'scan'])->middleware('throttle:120,1')->name('scan');
    Route::get('/visitor-photo/{visitor}', [GateTerminalController::class, 'photo'])->middleware('signed')->name('photo');
});

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [AdminAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AdminAuthController::class, 'login'])->middleware('throttle:5,1')->name('login.submit');

    Route::middleware('admin.auth')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::get('/dashboard/counts', [AdminDashboardController::class, 'counts'])->name('dashboard.counts');
        Route::patch('/dashboard/inside-count', [AdminDashboardController::class, 'updateInsideCount'])->name('dashboard.inside_count');
        Route::get('/attendance/entries', [AdminAttendanceController::class, 'entries'])->name('attendance.entries');
        Route::get('/attendance/summary', [AdminAttendanceController::class, 'summary'])->name('attendance.summary');
        Route::get('/attendance/detail', [AdminAttendanceController::class, 'detail'])->name('attendance.detail');
        Route::get('/attendance/detail-with-photo', [AdminAttendanceController::class, 'detailWithPhoto'])->name('attendance.detail_with_photo');
        Route::get('/revenue/summary', [AdminRevenueController::class, 'summary'])->name('revenue.summary');
        Route::get('/revenue/detail', [AdminRevenueController::class, 'detail'])->name('revenue.detail');
        Route::get('/configurations/event', [AdminEventConfigurationController::class, 'edit'])->name('configurations.event.edit');
        Route::put('/configurations/event', [AdminEventConfigurationController::class, 'update'])->name('configurations.event.update');
        Route::delete('/configurations/event', [AdminEventConfigurationController::class, 'destroy'])->name('configurations.event.destroy');
        Route::post('/configurations/event/registration-days', [AdminEventRegistrationDayController::class, 'store'])->name('configurations.event.days.store');
        Route::post('/configurations/event/registration-days/generate', [AdminEventRegistrationDayController::class, 'generate'])->name('configurations.event.days.generate');
        Route::put('/configurations/event/registration-days/{registrationDay}', [AdminEventRegistrationDayController::class, 'update'])->name('configurations.event.days.update');
        Route::patch('/configurations/event/registration-days/{registrationDay}/toggle', [AdminEventRegistrationDayController::class, 'toggle'])->name('configurations.event.days.toggle');
        Route::delete('/configurations/event/registration-days/{registrationDay}', [AdminEventRegistrationDayController::class, 'destroy'])->name('configurations.event.days.destroy');
        Route::get('/configurations/capacity', [AdminCapacityController::class, 'edit'])->name('configurations.capacity.edit');
        Route::put('/configurations/capacity', [AdminCapacityController::class, 'update'])->name('configurations.capacity.update');
        Route::get('/configurations/schedules', [AdminDailyReportScheduleController::class, 'index'])->name('configurations.schedules.index');
        Route::post('/configurations/schedules', [AdminDailyReportScheduleController::class, 'store'])->name('configurations.schedules.store');
        Route::put('/configurations/schedules/{schedule}', [AdminDailyReportScheduleController::class, 'update'])->name('configurations.schedules.update');
        Route::patch('/configurations/schedules/{schedule}/toggle', [AdminDailyReportScheduleController::class, 'toggle'])->name('configurations.schedules.toggle');
        Route::delete('/configurations/schedules/{schedule}', [AdminDailyReportScheduleController::class, 'destroy'])->name('configurations.schedules.destroy');

        Route::get('/configurations/categories', [AdminVisitorCategoryController::class, 'index'])->name('configurations.categories.index');
        Route::post('/configurations/categories', [AdminVisitorCategoryController::class, 'store'])->name('configurations.categories.store');
        Route::put('/configurations/categories/{category}', [AdminVisitorCategoryController::class, 'update'])->name('configurations.categories.update');
        Route::patch('/configurations/categories/{category}/toggle', [AdminVisitorCategoryController::class, 'toggleActive'])->name('configurations.categories.toggle');
        Route::delete('/configurations/categories/{category}', [AdminVisitorCategoryController::class, 'destroy'])->name('configurations.categories.destroy');
        Route::post('/configurations/categories/{category}/members', [AdminVisitorCategoryController::class, 'storeMember'])->name('configurations.categories.members.store');

        Route::get('/configurations/users', [AdminUserController::class, 'index'])->name('configurations.users.index');
        Route::post('/configurations/users', [AdminUserController::class, 'store'])->name('configurations.users.store');
        Route::put('/configurations/users/{user}', [AdminUserController::class, 'update'])->name('configurations.users.update');
        Route::patch('/configurations/users/{user}/toggle', [AdminUserController::class, 'toggleStatus'])->name('configurations.users.toggle');
        Route::delete('/configurations/users/{user}', [AdminUserController::class, 'destroy'])->name('configurations.users.destroy');
        Route::get('/visitors', [AdminVisitorController::class, 'index'])->name('visitors.index');
        Route::get('/exhibitors', [AdminExhibitorController::class, 'index'])->name('exhibitors.index');
        Route::get('/exhibitors/directory', [AdminExhibitorController::class, 'directory'])->name('exhibitors.directory');
        Route::post('/exhibitors', [AdminExhibitorController::class, 'store'])->name('exhibitors.store');
        Route::delete('/exhibitors/{exhibitorId}/members/{member}', [AdminExhibitorController::class, 'destroyMember'])->name('exhibitors.members.destroy');
        Route::get('/receipts', [AdminReceiptController::class, 'index'])->name('receipts.index');
        Route::post('/receipts/{visitor}/confirm', [AdminReceiptController::class, 'confirm'])->name('receipts.confirm');
        Route::get('/visitors/{visitor}', fn (VerifiedVisitor $visitor) => redirect()->route('admin.visitors.index'))->name('visitors.show');
        Route::patch('/visitors/{visitor}/checkin', [AdminVisitorController::class, 'toggleCheckin'])->name('visitors.checkin');
        Route::patch('/visitors/{visitor}', [AdminVisitorController::class, 'update'])->name('visitors.update');
        Route::delete('/visitors/{visitor}', [AdminVisitorController::class, 'destroy'])->name('visitors.destroy');
        Route::get('/visitors/{visitor}/photo', [AdminVisitorController::class, 'photo'])->name('visitors.photo');
        Route::get('/visitors/{visitor}/badge', [AdminVisitorController::class, 'badge'])->name('visitors.badge');
        Route::get('/visitors/{visitor}/back-photo', [AdminVisitorController::class, 'backPhoto'])->name('visitors.back_photo');
        Route::get('/visitors/{visitor}/selfie', [AdminVisitorController::class, 'selfie'])->name('visitors.selfie');
    });

    // Logout must remain reachable even when an old or mixed session fails
    // the access middleware; it always invalidates the whole browser session.
    Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');
});

Route::prefix('superadmin')->name('superadmin.')->group(function () {
    Route::get('/login', [SuperAdminAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [SuperAdminAuthController::class, 'login'])->middleware('throttle:5,1')->name('login.submit');

    Route::middleware('superadmin.auth')->group(function () {
        Route::get('/dashboard', [SuperAdminDashboardController::class, 'index'])->name('dashboard');
        Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
        Route::patch('/users/{user}/toggle', [AdminUserController::class, 'toggleStatus'])->name('users.toggle');
        Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');
        Route::post('/logout', [SuperAdminAuthController::class, 'logout'])->name('logout');
    });
});
