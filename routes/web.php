<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Controllers\TechnicalPlanController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

// Generic, model-agnostic file staging shared by any feature that needs
// attachments (see App\Concerns\HasAttachments).
Route::prefix('api/attachments')->name('attachments.')->group(function () {
    Route::post('/', [AttachmentController::class, 'store'])->name('store')->middleware('throttle:20,1');
    Route::get('{uuid}', [AttachmentController::class, 'show'])->name('show');
    Route::delete('{uuid}', [AttachmentController::class, 'destroy'])->name('destroy')->middleware('throttle:20,1');
});

// Inertia-rendered wizard pages.
Route::prefix('tehnikaplaan')->name('technical-plan.')->group(function () {
    Route::get('/', [TechnicalPlanController::class, 'index'])->name('index');
    Route::get('p/{plan:token}', [TechnicalPlanController::class, 'public'])->name('public');
});

// JSON API consumed by the technical-plan wizard frontend.
Route::prefix('api/tehnikaplaan')
    ->name('technical-plan.')
    ->middleware('throttle:200,1')
    ->group(function () {
        Route::post('/', [TechnicalPlanController::class, 'store'])->name('store');
        Route::post('lookup', [TechnicalPlanController::class, 'lookup'])->name('lookup');
        Route::get('performances', [TechnicalPlanController::class, 'performances'])->name('performances');
        Route::post('ai-review', [TechnicalPlanController::class, 'aiReview'])->name('ai')->middleware('throttle:15,10');
        Route::get('plans/{plan:token}', [TechnicalPlanController::class, 'show'])->name('show');
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
