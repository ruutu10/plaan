<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\ClaudeReasoningLogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FormatController;
use App\Http\Controllers\FormatPageController;
use App\Http\Controllers\MagicLoginController;
use App\Http\Controllers\ManualController;
use App\Http\Controllers\PerformanceController;
use App\Http\Controllers\Teams\TeamAdminController;
use App\Http\Controllers\Teams\TeamAdminMemberController;
use App\Http\Controllers\Teams\TeamAdminPageController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Controllers\TechnicalPlanController;
use App\Http\Controllers\Users\UserAdminController;
use App\Http\Controllers\Users\UserRoleController;
use App\Models\ClaudeReasoningLog;
use App\Models\Performance;
use App\Models\TechnicalPlan;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

// The user manual. Open to everyone: a performer who has not signed in yet is
// exactly the reader most likely to need it.
Route::get('abi', ManualController::class)->name('manual');

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

// The overview of the plans that have been written, whatever state they are in.
// Open to every signed-in user, because how far it reaches is the listing's own
// question rather than the door's: holders of the view-all permission (the
// "technician" role) are shown the whole house, everybody else their own plans
// and their groups' — see App\Models\TechnicalPlan::listableBy().
Route::get('technical-plans', [TechnicalPlanController::class, 'overview'])
    ->middleware(['auth'])
    ->name('technical-plans.index');

// A single plan's details, opened from a row of the overview above. Guarded by
// the same reach as the listing, checked in the controller against the plan.
Route::get('technical-plans/{plan:token}', [TechnicalPlanController::class, 'showDetails'])
    ->middleware(['auth'])
    ->name('technical-plans.show');

// Changing a plan's status from its details page is a right of its own — held
// by the same "technician" role, but not implied by being able to merely view it.
Route::patch('technical-plans/{plan:token}', [TechnicalPlanController::class, 'updateStatus'])
    ->middleware(['auth', 'can:'.TechnicalPlan::EDIT_ALL_PERMISSION])
    ->name('technical-plans.update-status');

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

// Inertia-rendered format-management pages. Each is a shell: what it lists and
// what it saves travels over the JSON API below, so the browser is served by
// the same endpoints as any other client.
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('formats', [FormatPageController::class, 'index'])->name('formats.index');
    Route::get('formats/{format}/edit', [FormatPageController::class, 'edit'])->name('formats.edit');
});

// JSON API for format management. A user reaches the formats of the groups they
// belong to and no others; holders of App\Models\Format::EDIT_ALL_PERMISSION
// reach every format in the house.
Route::prefix('api/formats')
    ->name('api.formats.')
    ->middleware(['auth', 'verified', 'throttle:200,1'])
    ->group(function () {
        Route::get('/', [FormatController::class, 'index'])->name('index');
        Route::post('/', [FormatController::class, 'store'])->name('store');
        Route::get('{format}', [FormatController::class, 'show'])->name('show');
        Route::patch('{format}', [FormatController::class, 'update'])->name('update');
        Route::delete('{format}', [FormatController::class, 'destroy'])->name('destroy');

        // What the AI made of the cards this format was built from. A debugging
        // aid for the house's own people, so the permission alone governs it —
        // the listings decide which formats a user is offered it on.
        Route::get('{format}/claude-logs', [ClaudeReasoningLogController::class, 'forFormat'])
            ->middleware('can:'.ClaudeReasoningLog::VIEW_PERMISSION)
            ->name('claude-logs');

        // A format's dated performances. Scoped bindings tie the performance to the format
        // in the URL, so one format's id can never reach another's performance.
        Route::prefix('{format}/performances')
            ->name('performances.')
            ->scopeBindings()
            ->group(function () {
                Route::get('/', [PerformanceController::class, 'index'])->name('index');
                Route::post('/', [PerformanceController::class, 'store'])->name('store');
                Route::patch('{performance}', [PerformanceController::class, 'update'])->name('update');
                Route::delete('{performance}', [PerformanceController::class, 'destroy'])->name('destroy');

                Route::get('{performance}/claude-logs', [ClaudeReasoningLogController::class, 'forPerformance'])
                    ->middleware('can:'.ClaudeReasoningLog::VIEW_PERMISSION)
                    ->name('claude-logs');
            });
    });

// The crew's overview of every performance in the house, whatever format it
// belongs to and whichever group plays it. Opened by the performance edit-all
// permission (the "technician" role), which is also what decides how far the
// listing itself reaches; everybody else keeps to their own groups' formats above.
Route::get('performances', [PerformanceController::class, 'overview'])
    ->middleware(['auth', 'verified', 'can:'.Performance::EDIT_ALL_PERMISSION])
    ->name('admin.performances.index');

// The audit trail every state change in the house is kept in — see
// App\Concerns\LogsModelActivity. Open to the technicians alone.
Route::get('audit-log', [AuditLogController::class, 'index'])
    ->middleware(['auth', 'verified', 'can:'.AuditLogController::VIEW_PERMISSION])
    ->name('admin.audit-log.index');

// Inertia-rendered team-management pages, shells like the show ones above.
// These are the admin's view of the groups themselves; a user's own team
// settings live under /settings/teams (see routes/settings.php).
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('teams', [TeamAdminPageController::class, 'index'])->name('admin.teams.index');
    Route::get('teams/{team:id}/edit', [TeamAdminPageController::class, 'edit'])->name('admin.teams.edit');
});

// JSON API for team management. A user reaches the teams they belong to and no
// others; holders of App\Models\Team::EDIT_ALL_PERMISSION reach every team in
// the house. The team is bound by id rather than its slug, which follows the
// name and would go stale the moment a team is renamed here.
Route::prefix('api/teams')
    ->name('api.teams.')
    ->middleware(['auth', 'verified', 'throttle:200,1'])
    ->group(function () {
        Route::get('/', [TeamAdminController::class, 'index'])->name('index');
        Route::post('/', [TeamAdminController::class, 'store'])->name('store');
        Route::get('{team:id}', [TeamAdminController::class, 'show'])->name('show');
        Route::patch('{team:id}', [TeamAdminController::class, 'update'])->name('update');
        Route::delete('{team:id}', [TeamAdminController::class, 'destroy'])->name('destroy');

        // Who belongs to the team, and as what.
        Route::prefix('{team:id}/members')
            ->name('members.')
            ->group(function () {
                Route::post('/', [TeamAdminMemberController::class, 'store'])->name('store');
                Route::patch('{user}', [TeamAdminMemberController::class, 'update'])->name('update');
                Route::delete('{user}', [TeamAdminMemberController::class, 'destroy'])->name('destroy');
            });
    });

// Inertia-rendered account-management pages, shells like the team ones above.
// Everything here belongs to the technicians alone: the listing is the whole
// house, and the roles handed out on it carry every right the application has.
// Unlike a team, an account has no owner to ask, so the permission decides the
// whole screen and the guard sits here rather than inside the controller.
Route::middleware(['auth', 'verified', 'can:'.User::MANAGE_PERMISSION])->group(function () {
    Route::get('users', [UserAdminController::class, 'overview'])->name('admin.users.index');
    Route::get('users/{user}/edit', [UserAdminController::class, 'edit'])->name('admin.users.edit');
});

// JSON API behind those pages, guarded by the same permission rather than by
// what the reader belongs to — an account is nobody's to keep but the crew's.
// Handing out a role is a right of its own on top of it; see UserRoleController.
Route::prefix('api/users')
    ->name('api.users.')
    ->middleware(['auth', 'verified', 'can:'.User::MANAGE_PERMISSION, 'throttle:200,1'])
    ->group(function () {
        Route::get('/', [UserAdminController::class, 'index'])->name('index');
        Route::get('{user}', [UserAdminController::class, 'show'])->name('show');
        Route::patch('{user}', [UserAdminController::class, 'update'])->name('update');

        // Which roles the account holds. The role is named rather than
        // numbered: the name is what the permission tables are written with,
        // and what a log line has to be readable by a year from now.
        Route::prefix('{user}/roles')
            ->name('roles.')
            ->group(function () {
                Route::post('/', [UserRoleController::class, 'store'])->name('store');

                // Naming the role turns on Laravel's implicit scoping, which
                // would look the role up through the ones the account already
                // holds — and answer "no such page" for a role it does not.
                // Taking away a role nobody holds is the state being asked for,
                // not a mistake, so the role is read on its own and a repeated
                // click changes nothing. Only a role that exists nowhere 404s.
                Route::delete('{role:name}', [UserRoleController::class, 'destroy'])
                    ->withoutScopedBindings()
                    ->name('destroy');
            });
    });

// Not scoped to a team: the dashboard shows the signed-in user's own
// invitations, upcoming performances and plans, none of which belong to a
// particular team, so it must stay reachable for a user who is on none.
Route::get('dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::get('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth-sso.php';
