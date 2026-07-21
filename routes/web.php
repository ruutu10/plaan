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
Route::post('attachments', [AttachmentController::class, 'store'])->name('attachments.store');
Route::get('attachments/{uuid}', [AttachmentController::class, 'show'])->name('attachments.show');
Route::delete('attachments/{uuid}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');

Route::prefix('tehnikaplaan')->name('technical-plan.')->group(function () {
    Route::get('/', [TechnicalPlanController::class, 'index'])->name('index');
    Route::post('/', [TechnicalPlanController::class, 'store'])->name('store');
    Route::post('lookup', [TechnicalPlanController::class, 'lookup'])->name('lookup');
    Route::get('performances', [TechnicalPlanController::class, 'performances'])->name('performances');
    Route::post('ai-review', [TechnicalPlanController::class, 'aiReview'])->name('ai');
    Route::get('plans/{plan:token}', [TechnicalPlanController::class, 'show'])->name('show');
    Route::get('p/{plan:token}', [TechnicalPlanController::class, 'public'])->name('public');
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
