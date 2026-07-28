<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MagicLoginController;
use App\Http\Controllers\PerformanceController;
use App\Http\Controllers\ShowController;
use App\Http\Controllers\ShowPageController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Controllers\TechnicalPlanController;
use App\Http\Middleware\EnsureTeamMembership;
use App\Models\TechnicalPlan;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

// Generic, model-agnostic file staging shared by any feature that needs
// attachments (see App\Concerns\HasAttachments).
Route::prefix('api/attachments')->name('attachments.')->group(function () {
    // Putting a file on the server — and discarding one again — is only for
    // signed-in users; every feature that offers uploads sits behind a login.
    Route::middleware('auth')->group(function () {
        Route::post('/', [AttachmentController::class, 'store'])->name('store')->middleware('throttle:20,1');
        Route::delete('{uuid}', [AttachmentController::class, 'destroy'])->name('destroy')->middleware('throttle:20,1');
    });

    // Reading a stored file stays open: a plan shared by its public link must
    // be readable — attachments included — without an account.
    Route::get('{uuid}', [AttachmentController::class, 'show'])->name('show');
});

// Inertia-rendered wizard pages.
Route::prefix('tehnikaplaan')->name('technical-plan.')->group(function () {
    Route::get('/', [TechnicalPlanController::class, 'index'])->name('index');
    Route::get('p/{plan:token}', [TechnicalPlanController::class, 'public'])->name('public');
});

// The technical crew's overview of every plan that has been written, whatever
// state it is in. Closed to everyone but the holders of the view-all permission
// (the "technician" role).
Route::get('technical-plans', [TechnicalPlanController::class, 'overview'])
    ->middleware(['auth', 'can:'.TechnicalPlan::VIEW_ALL_PERMISSION])
    ->name('technical-plans.index');

// JSON API consumed by the technical-plan wizard frontend.
Route::prefix('api/tehnikaplaan')
    ->name('technical-plan.')
    ->group(function () {
        // The first step of the flow is always to log the user in: e-mailing a
        // magic link is the only action available before authentication.
        Route::post('login', [MagicLoginController::class, 'send'])->name('login')->middleware('throttle:6,1');

        // Every plan action requires an authenticated user.
        Route::middleware(['auth', 'throttle:200,1'])
            ->group(function () {
                Route::post('/', [TechnicalPlanController::class, 'store'])->name('store');
                Route::post('lookup', [TechnicalPlanController::class, 'lookup'])->name('lookup');
                Route::get('performances', [TechnicalPlanController::class, 'performances'])->name('performances');
                Route::post('ai-review', [TechnicalPlanController::class, 'aiReview'])->name('ai')->middleware('throttle:15,10');
                Route::get('plans/{plan:token}', [TechnicalPlanController::class, 'show'])->name('show');
                Route::post('plans/{plan:token}/copy', [TechnicalPlanController::class, 'copy'])->name('copy');
            });
    });

// Inertia-rendered show-management pages. Each is a shell: what it lists and
// what it saves travels over the JSON API below, so the browser is served by
// the same endpoints as any other client.
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('shows', [ShowPageController::class, 'index'])->name('shows.index');
    Route::get('shows/{show}/edit', [ShowPageController::class, 'edit'])->name('shows.edit');
});

// JSON API for show management. A user reaches the shows of the groups they
// belong to and no others; holders of App\Models\Show::EDIT_ALL_PERMISSION
// reach every show in the house.
Route::prefix('api/shows')
    ->name('api.shows.')
    ->middleware(['auth', 'verified', 'throttle:200,1'])
    ->group(function () {
        Route::get('/', [ShowController::class, 'index'])->name('index');
        Route::post('/', [ShowController::class, 'store'])->name('store');
        Route::get('{show}', [ShowController::class, 'show'])->name('show');
        Route::patch('{show}', [ShowController::class, 'update'])->name('update');
        Route::delete('{show}', [ShowController::class, 'destroy'])->name('destroy');

        // A show's dated performances. Scoped bindings tie the performance to the show
        // in the URL, so one show's id can never reach another's performance.
        Route::prefix('{show}/performances')
            ->name('performances.')
            ->scopeBindings()
            ->group(function () {
                Route::get('/', [PerformanceController::class, 'index'])->name('index');
                Route::post('/', [PerformanceController::class, 'store'])->name('store');
                Route::patch('{performance}', [PerformanceController::class, 'update'])->name('update');
                Route::delete('{performance}', [PerformanceController::class, 'destroy'])->name('destroy');
            });
    });

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
    });

Route::middleware(['auth'])->group(function () {
    Route::get('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

require __DIR__.'/settings.php';
