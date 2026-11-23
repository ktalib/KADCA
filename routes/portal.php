<?php

use App\Http\Controllers\Portal\ApplicationController;
use App\Http\Controllers\Portal\AuthController;
use App\Http\Controllers\Portal\DashboardController;
use App\Http\Controllers\Portal\InspectionController;
use App\Http\Controllers\Portal\LandingController;
use App\Http\Controllers\Portal\ProfileController;
use App\Http\Controllers\Portal\ProgressController;
use App\Http\Middleware\PortalAuthenticate;
use App\Http\Middleware\PortalGuest;
use Illuminate\Support\Facades\Route;

/*
| KADCA applicant portal (KLAES-CONTROL): applicants submit building plans, follow progress, upload construction
| progress as they build and book site inspections (briefing: "Portal" beside the DD Arc D step).
|
| Outside the staff `auth` guard, like KLAES routes/laas.php. The applicant is signed in by App\Support\Portal\PortalSession
| (session only while the portal is UI first, user 2026-11-23). Saves answer "Saved (preview)" and store nothing.
*/

Route::prefix('portal')->name('portal.')->group(function () {
    // Public landing page with Sign in / Create account (user 2026-11-23); a signed-in applicant goes to the dashboard.
    Route::get('/', LandingController::class)->name('home');

    Route::middleware(PortalGuest::class)->group(function () {
        Route::get('login', [AuthController::class, 'showLogin'])->name('login');
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login.submit');
        Route::post('demo', [AuthController::class, 'demo'])->middleware('throttle:10,1')->name('demo');
        Route::get('register', [AuthController::class, 'showRegister'])->name('register');
        Route::post('register', [AuthController::class, 'register'])->middleware('throttle:6,1')->name('register.submit');
        Route::get('register/verify', [AuthController::class, 'showVerify'])->name('verify');
        Route::post('register/verify', [AuthController::class, 'verify'])->middleware('throttle:10,1')->name('verify.submit');
    });

    Route::middleware(PortalAuthenticate::class)->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');

        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('bills', [DashboardController::class, 'bills'])->name('bills');
        Route::get('notifications', [DashboardController::class, 'notifications'])->name('notifications');

        Route::get('applications/create', [ApplicationController::class, 'create'])->name('applications.create');
        Route::post('applications', [ApplicationController::class, 'store'])->name('applications.store');
        Route::get('applications/track', [ApplicationController::class, 'track'])->name('applications.track');
        // Application numbers carry slashes (BPA-APP/2026/00001), so the reference takes the rest of the path.
        Route::get('applications/{reference}', [ApplicationController::class, 'show'])
            ->where('reference', '[A-Za-z0-9][A-Za-z0-9\-/]*')->name('applications.show');

        // KLAES land files for the selector: file numbers only, no phone or address (portal copy of klaes.files.*).
        Route::get('land-files/search', [ApplicationController::class, 'fileSearch'])->middleware('throttle:60,1')->name('files.search');
        Route::get('land-files/{id}', [ApplicationController::class, 'fileShow'])->whereNumber('id')->name('files.show');

        Route::get('construction-progress', [ProgressController::class, 'index'])->name('progress.index');
        Route::post('construction-progress', [ProgressController::class, 'store'])->name('progress.store');

        Route::get('inspections', [InspectionController::class, 'index'])->name('inspections.index');
        Route::post('inspections', [InspectionController::class, 'store'])->name('inspections.store');

        Route::get('profile', [ProfileController::class, 'show'])->name('profile');
        Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('profile/password', [ProfileController::class, 'password'])->name('profile.password');
    });
});
