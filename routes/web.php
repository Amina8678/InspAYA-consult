<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Site\ConsultantController;
use App\Http\Controllers\Site\CoreValueController;
use App\Http\Controllers\Site\InsightController;
use App\Http\Controllers\Site\PageController;
use App\Http\Controllers\Site\ServiceController as SiteServiceController;
use App\Http\Controllers\Site\ContactController;
use App\Http\Controllers\Admin\AccountPasswordController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\SiteSettingsController;
use App\Http\Controllers\Auth\AdminLoginController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Admin\DashboardController;

// Slugs are lowercase words joined by single hyphens; anything else 404s
// without touching the database. Must precede the routes it applies to.
Route::pattern('slug', '[a-z0-9]+(?:-[a-z0-9]+)*');

// Public site (read-only). URL, route name and view name share one term per
// section, following the SRS Appendix A site map. See docs/frontend-contract.md.
Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/services', [SiteServiceController::class, 'index'])->name('services.index');
Route::get('/services/{slug}', [SiteServiceController::class, 'show'])->name('services.show');
Route::get('/consultants', [ConsultantController::class, 'index'])->name('consultants.index');
Route::get('/core-values', [CoreValueController::class, 'index'])->name('core-values.index');
Route::get('/insights', [InsightController::class, 'index'])->name('insights.index');
Route::get('/insights/{slug}', [InsightController::class, 'show'])->name('insights.show');
Route::get('/contact', [PageController::class, 'contact'])->name('contact');
Route::get('/privacy-policy', [PageController::class, 'privacyPolicy'])->name('privacy-policy');
Route::get('/terms-of-service', [PageController::class, 'termsOfService'])->name('terms-of-service');

// Contact form submission (FR-CONT), rate limited per IP.
Route::post('/contact', [ContactController::class, 'store'])
    ->middleware('throttle:contact')
    ->name('contact.store');

// Admin auth (guests only; signed-in users are sent to the dashboard)
Route::middleware('guest')->prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [AdminLoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AdminLoginController::class, 'login'])->name('login.submit');

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:password-reset')
        ->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:password-reset')
        ->name('password.store');
});

// Logout is POST only, so a link or image tag can't sign anyone out.
Route::post('/admin/logout', [AdminLoginController::class, 'logout'])
    ->middleware('auth')
    ->name('admin.logout');

// Admin (protected). `active` signs out deactivated accounts; `auth.session`
// ends other sessions when a password changes.
Route::middleware(['auth', 'auth.session', 'active'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/account/password', [AccountPasswordController::class, 'edit'])->name('account.password.edit');
    Route::put('/account/password', [AccountPasswordController::class, 'update'])->name('account.password.update');

    // Media library (FR-ADM-09). Middleware enforces plan §6; the controller
    // re-checks the same policies.
    Route::get('/media', [MediaController::class, 'index'])->middleware('permission:media.view')->name('media.index');
    Route::post('/media', [MediaController::class, 'store'])->middleware('permission:media.upload')->name('media.store');
    Route::get('/media/{media}/edit', [MediaController::class, 'edit'])->middleware('can:update,media')->name('media.edit');
    Route::put('/media/{media}', [MediaController::class, 'update'])->middleware('can:update,media')->name('media.update');
    Route::get('/media/{media}/delete', [MediaController::class, 'confirmDelete'])->middleware('can:delete,media')->name('media.delete');
    Route::delete('/media/{media}', [MediaController::class, 'destroy'])->middleware('can:delete,media')->name('media.destroy');

    // Site settings (FR-ADM-13).
    Route::get('/settings', [SiteSettingsController::class, 'edit'])->middleware('permission:settings.manage')->name('settings.edit');
    Route::put('/settings', [SiteSettingsController::class, 'update'])->middleware('permission:settings.manage')->name('settings.update');
});
