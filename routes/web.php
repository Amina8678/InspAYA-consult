<?php

use App\Http\Controllers\Admin\AccountPasswordController;
use App\Http\Controllers\Admin\BlogPostController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ConsultantController as AdminConsultantController;
use App\Http\Controllers\Admin\ContactSubmissionController;
use App\Http\Controllers\Admin\ContactSubmissionNoteController;
use App\Http\Controllers\Admin\CoreValueController as AdminCoreValueController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\PageController as AdminPageController;
use App\Http\Controllers\Admin\ServiceController as AdminServiceController;
use App\Http\Controllers\Admin\SiteSettingsController;
use App\Http\Controllers\Admin\TagController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AdminLoginController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Site\ConsultantController;
use App\Http\Controllers\Site\ContactController;
use App\Http\Controllers\Site\CoreValueController;
use App\Http\Controllers\Site\InsightController;
use App\Http\Controllers\Site\PageController;
use App\Http\Controllers\Site\ServiceController as SiteServiceController;
use Illuminate\Support\Facades\Route;

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

    // Pages (FR-ADM-04). Editors: list and edit content (drafts);
    // Admin+: create, delete; publishing needs content.publish (plan §6, D12).
    Route::get('/pages', [AdminPageController::class, 'index'])->middleware('permission:pages.edit|pages.manage')->name('pages.index');
    Route::get('/pages/create', [AdminPageController::class, 'create'])->middleware('permission:pages.manage')->name('pages.create');
    Route::post('/pages', [AdminPageController::class, 'store'])->middleware('permission:pages.manage')->name('pages.store');
    Route::get('/pages/{page}/edit', [AdminPageController::class, 'edit'])->middleware('permission:pages.edit|pages.manage')->name('pages.edit');
    Route::put('/pages/{page}', [AdminPageController::class, 'update'])->middleware('permission:pages.edit|pages.manage')->name('pages.update');
    Route::get('/pages/{page}/delete', [AdminPageController::class, 'confirmDelete'])->middleware('permission:pages.manage')->name('pages.delete');
    Route::delete('/pages/{page}', [AdminPageController::class, 'destroy'])->middleware('permission:pages.manage')->name('pages.destroy');

    // Services (FR-SVC, FR-ADM-05). Editors: list and edit content;
    // Admin+: create, delete, reorder, activate, assign consultants (plan §6).
    Route::get('/services', [AdminServiceController::class, 'index'])->middleware('permission:services.edit|services.manage')->name('services.index');
    Route::get('/services/create', [AdminServiceController::class, 'create'])->middleware('permission:services.manage')->name('services.create');
    Route::post('/services', [AdminServiceController::class, 'store'])->middleware('permission:services.manage')->name('services.store');
    Route::get('/services/{service}/edit', [AdminServiceController::class, 'edit'])->middleware('permission:services.edit|services.manage')->name('services.edit');
    Route::put('/services/{service}', [AdminServiceController::class, 'update'])->middleware('permission:services.edit|services.manage')->name('services.update');
    Route::post('/services/{service}/move', [AdminServiceController::class, 'move'])->middleware('permission:services.manage')->name('services.move');
    Route::get('/services/{service}/delete', [AdminServiceController::class, 'confirmDelete'])->middleware('permission:services.manage')->name('services.delete');
    Route::delete('/services/{service}', [AdminServiceController::class, 'destroy'])->middleware('permission:services.manage')->name('services.destroy');

    // Consultants (FR-TEAM, FR-ADM-07). Editors: list and edit content;
    // Admin+: create, delete, reorder, activate, assign services (plan §6).
    Route::get('/consultants', [AdminConsultantController::class, 'index'])->middleware('permission:consultants.edit|consultants.manage')->name('consultants.index');
    Route::get('/consultants/create', [AdminConsultantController::class, 'create'])->middleware('permission:consultants.manage')->name('consultants.create');
    Route::post('/consultants', [AdminConsultantController::class, 'store'])->middleware('permission:consultants.manage')->name('consultants.store');
    Route::get('/consultants/{consultant}/edit', [AdminConsultantController::class, 'edit'])->middleware('permission:consultants.edit|consultants.manage')->name('consultants.edit');
    Route::put('/consultants/{consultant}', [AdminConsultantController::class, 'update'])->middleware('permission:consultants.edit|consultants.manage')->name('consultants.update');
    Route::post('/consultants/{consultant}/move', [AdminConsultantController::class, 'move'])->middleware('permission:consultants.manage')->name('consultants.move');
    Route::get('/consultants/{consultant}/delete', [AdminConsultantController::class, 'confirmDelete'])->middleware('permission:consultants.manage')->name('consultants.delete');
    Route::delete('/consultants/{consultant}', [AdminConsultantController::class, 'destroy'])->middleware('permission:consultants.manage')->name('consultants.destroy');

    // Core values (FR-VAL, FR-ADM-06). Editors: list and edit content;
    // Admin+: create, delete, reorder, activate (plan §6).
    Route::get('/core-values', [AdminCoreValueController::class, 'index'])->middleware('permission:values.edit|values.manage')->name('core-values.index');
    Route::get('/core-values/create', [AdminCoreValueController::class, 'create'])->middleware('permission:values.manage')->name('core-values.create');
    Route::post('/core-values', [AdminCoreValueController::class, 'store'])->middleware('permission:values.manage')->name('core-values.store');
    Route::get('/core-values/{coreValue}/edit', [AdminCoreValueController::class, 'edit'])->middleware('permission:values.edit|values.manage')->name('core-values.edit');
    Route::put('/core-values/{coreValue}', [AdminCoreValueController::class, 'update'])->middleware('permission:values.edit|values.manage')->name('core-values.update');
    Route::post('/core-values/{coreValue}/move', [AdminCoreValueController::class, 'move'])->middleware('permission:values.manage')->name('core-values.move');
    Route::get('/core-values/{coreValue}/delete', [AdminCoreValueController::class, 'confirmDelete'])->middleware('permission:values.manage')->name('core-values.delete');
    Route::delete('/core-values/{coreValue}', [AdminCoreValueController::class, 'destroy'])->middleware('permission:values.manage')->name('core-values.destroy');

    // Blog posts (FR-BLOG, FR-ADM-08). Everyone who writes posts can list
    // and create; editing needs posts.edit-own|posts.edit-any (BlogPostPolicy
    // narrows this to "own drafts only" for Authors); deleting needs
    // posts.delete (D11: Authors never delete, even their own).
    Route::get('/posts', [BlogPostController::class, 'index'])->middleware('permission:posts.create|posts.edit-any')->name('posts.index');
    Route::get('/posts/create', [BlogPostController::class, 'create'])->middleware('permission:posts.create')->name('posts.create');
    Route::post('/posts', [BlogPostController::class, 'store'])->middleware('permission:posts.create')->name('posts.store');
    Route::get('/posts/{post}/edit', [BlogPostController::class, 'edit'])->middleware('permission:posts.edit-own|posts.edit-any')->name('posts.edit');
    Route::put('/posts/{post}', [BlogPostController::class, 'update'])->middleware('permission:posts.edit-own|posts.edit-any')->name('posts.update');
    Route::get('/posts/{post}/delete', [BlogPostController::class, 'confirmDelete'])->middleware('permission:posts.delete')->name('posts.delete');
    Route::delete('/posts/{post}', [BlogPostController::class, 'destroy'])->middleware('permission:posts.delete')->name('posts.destroy');

    // Blog categories and tags (FR-BLOG-01, plan §6 row 21).
    Route::get('/categories', [CategoryController::class, 'index'])->middleware('permission:taxonomy.manage|posts.create')->name('categories.index');
    Route::get('/categories/create', [CategoryController::class, 'create'])->middleware('permission:taxonomy.manage')->name('categories.create');
    Route::post('/categories', [CategoryController::class, 'store'])->middleware('permission:taxonomy.manage')->name('categories.store');
    Route::get('/categories/{category}/edit', [CategoryController::class, 'edit'])->middleware('permission:taxonomy.manage')->name('categories.edit');
    Route::put('/categories/{category}', [CategoryController::class, 'update'])->middleware('permission:taxonomy.manage')->name('categories.update');
    Route::get('/categories/{category}/delete', [CategoryController::class, 'confirmDelete'])->middleware('permission:taxonomy.manage')->name('categories.delete');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->middleware('permission:taxonomy.manage')->name('categories.destroy');

    Route::get('/tags', [TagController::class, 'index'])->middleware('permission:taxonomy.manage|posts.create')->name('tags.index');
    Route::get('/tags/create', [TagController::class, 'create'])->middleware('permission:taxonomy.manage')->name('tags.create');
    Route::post('/tags', [TagController::class, 'store'])->middleware('permission:taxonomy.manage')->name('tags.store');
    Route::get('/tags/{tag}/edit', [TagController::class, 'edit'])->middleware('permission:taxonomy.manage')->name('tags.edit');
    Route::put('/tags/{tag}', [TagController::class, 'update'])->middleware('permission:taxonomy.manage')->name('tags.update');
    Route::get('/tags/{tag}/delete', [TagController::class, 'confirmDelete'])->middleware('permission:taxonomy.manage')->name('tags.delete');
    Route::delete('/tags/{tag}', [TagController::class, 'destroy'])->middleware('permission:taxonomy.manage')->name('tags.destroy');

    // Enquiry inbox (FR-CONT-04/06, FR-ADM-10). Editors view and respond
    // (status, notes); only Admin+ assign or delete (plan §6 rows 27-31).
    Route::get('/enquiries', [ContactSubmissionController::class, 'index'])->middleware('permission:enquiries.view')->name('enquiries.index');
    Route::get('/enquiries/{submission}', [ContactSubmissionController::class, 'show'])->middleware('permission:enquiries.view')->name('enquiries.show');
    Route::put('/enquiries/{submission}', [ContactSubmissionController::class, 'update'])->middleware('permission:enquiries.respond|enquiries.assign')->name('enquiries.update');
    Route::get('/enquiries/{submission}/delete', [ContactSubmissionController::class, 'confirmDelete'])->middleware('permission:enquiries.delete')->name('enquiries.delete');
    Route::delete('/enquiries/{submission}', [ContactSubmissionController::class, 'destroy'])->middleware('permission:enquiries.delete')->name('enquiries.destroy');

    // Internal enquiry notes (D1). Adding/editing needs enquiries.respond
    // (ContactSubmissionNotePolicy narrows editing to the note's own
    // author); deleting needs enquiries.delete OR being the note's own
    // author, so the route also admits enquiries.respond, and
    // ContactSubmissionNotePolicy::delete narrows it from there.
    Route::post('/enquiries/{submission}/notes', [ContactSubmissionNoteController::class, 'store'])->middleware('permission:enquiries.respond')->name('enquiries.notes.store');
    Route::get('/enquiries/{submission}/notes/{note}/edit', [ContactSubmissionNoteController::class, 'edit'])->middleware('permission:enquiries.respond')->name('enquiries.notes.edit');
    Route::put('/enquiries/{submission}/notes/{note}', [ContactSubmissionNoteController::class, 'update'])->middleware('permission:enquiries.respond')->name('enquiries.notes.update');
    Route::get('/enquiries/{submission}/notes/{note}/delete', [ContactSubmissionNoteController::class, 'confirmDelete'])->middleware('permission:enquiries.delete|enquiries.respond')->name('enquiries.notes.delete');
    Route::delete('/enquiries/{submission}/notes/{note}', [ContactSubmissionNoteController::class, 'destroy'])->middleware('permission:enquiries.delete|enquiries.respond')->name('enquiries.notes.destroy');

    // CMS users (FR-ADM-11). No delete route: UserPolicy::delete() always
    // refuses, so deactivation (via update, plan §6 rows 1-6) is the only
    // removal path (D6).
    Route::get('/users', [UserController::class, 'index'])->middleware('permission:users.view')->name('users.index');
    Route::get('/users/create', [UserController::class, 'create'])->middleware('permission:users.create')->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->middleware('permission:users.create')->name('users.store');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->middleware('permission:users.update')->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->middleware('permission:users.update')->name('users.update');

    // Site settings (FR-ADM-13).
    Route::get('/settings', [SiteSettingsController::class, 'edit'])->middleware('permission:settings.manage')->name('settings.edit');
    Route::put('/settings', [SiteSettingsController::class, 'update'])->middleware('permission:settings.manage')->name('settings.update');
});
