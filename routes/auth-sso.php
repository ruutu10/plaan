<?php

use App\Http\Controllers\AuthentikController;
use Illuminate\Support\Facades\Route;

// Authentik SSO: one route the "Continue with Authentik" link and the
// silent prompt=none check both redirect through, and a shared callback
// that logs the user in either way. See App\Actions\Sso\RedirectToAuthentik
// and App\Actions\Sso\AttemptSilentAuthentikLogin.
Route::prefix('auth/authentik')->name('auth.authentik.')->group(function () {
    Route::get('redirect', [AuthentikController::class, 'redirect'])->name('redirect');
    Route::get('callback', [AuthentikController::class, 'callback'])->name('callback');
});
