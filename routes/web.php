<?php

use App\Http\Controllers\Archive\DocWareController;
use App\Http\Controllers\Bpa\ApplicationController;
use App\Http\Controllers\Bpa\BillController;
use App\Http\Controllers\Bpa\BuildingPlanController;
use App\Http\Controllers\Bpa\CommissioningController;
use App\Http\Controllers\Bpa\DeskController;
use App\Http\Controllers\Bpa\PaymentController;
use App\Http\Controllers\Bpa\PaymentReportController;
use App\Http\Controllers\Dms\IndexingController;
use App\Http\Controllers\Dms\PageTypingController;
use App\Http\Controllers\Dms\ScanUploadController;
use App\Http\Controllers\Klaes\IndexedFileController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\Tracking\DepartmentMovementController;
use App\Http\Controllers\Tracking\FileTrackingController;
use App\Http\Controllers\Tracking\LogFileController;
use App\Http\Controllers\Tracking\QuickSearchController;
use App\Http\Controllers\Tracking\UnitMovementController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// The KADCA dashboard: figures for every stage of Building Plan Approval (KLAES module-dashboard design).
Route::get('/dashboard', fn (\App\Services\Dashboard\KadcaDashboard $dashboard) => view('dashboard', ['d' => $dashboard->build()]))
    ->middleware(['auth', 'verified'])->name('dashboard');

// My account: read-only, the details are KLAES's (dbo.users) and are changed in KLAES.
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
});

// Menu items from the KLAES menu document that are not built yet land here instead of a dead link.
Route::get('/not-built', fn (\Illuminate\Http\Request $request) => view('not-built', [
    'item' => mb_substr((string) $request->query('item', ''), 0, 200),
]))->middleware('auth')->name('not-built');

// Assessment Stage: Building Plan Approval applications and their application bill.
Route::middleware('auth')->prefix('assessment')->name('bpa.')->group(function () {
    Route::get('/applications', [ApplicationController::class, 'index'])->name('applications.index');
    Route::get('/applications/create', [ApplicationController::class, 'create'])->name('applications.create');
    Route::post('/applications', [ApplicationController::class, 'store'])->name('applications.store');
    Route::get('/applications/{application}', [ApplicationController::class, 'show'])->name('applications.show');
    Route::get('/applications/{application}/files/{file}', [ApplicationController::class, 'download'])->name('applications.files.download');
    Route::get('/bills', [BillController::class, 'index'])->name('bills.index');
});

// Architecture: confirm the application bill was paid, commission the BP file, and the commissioned building plans.
Route::middleware('auth')->prefix('architecture')->name('bpa.')->group(function () {
    Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::post('/payments/{bill}', [PaymentController::class, 'store'])->name('payments.store');
    Route::get('/commissioning', [CommissioningController::class, 'index'])->name('commissioning.index');
    Route::post('/commissioning/{application}', [CommissioningController::class, 'store'])->name('commissioning.store');
    Route::get('/building-plans', [BuildingPlanController::class, 'index'])->name('building-plans.index');
    Route::post('/building-plans/{application}/resend', [BuildingPlanController::class, 'resend'])->name('building-plans.resend');
});

// Bills & Payments: the Payments Report (borrowed from KLAES's Payments Report) and its CSV.
Route::middleware('auth')->prefix('bills-payments')->name('bpa.')->group(function () {
    Route::get('/payments-report', [PaymentReportController::class, 'index'])->name('payments-report');
    Route::get('/payments-report.csv', [PaymentReportController::class, 'csv'])->name('payments-report.csv');
});

// Reports: Building Plan Approval figures for a date range (borrowed from KLAES's Survey Reports) and the applications register.
Route::middleware('auth')->prefix('reports')->name('reports.')->group(function () {
    Route::get('/', [ReportController::class, 'index'])->name('index');
    Route::get('/applications-register.csv', [ReportController::class, 'register'])->name('register');
});

// File Tracking (copied in concept from KLAES): files and mails, their QR labels and movements between units.
Route::middleware('auth')->prefix('file-tracking')->name('tracking.')->group(function () {
    Route::get('/', [FileTrackingController::class, 'dashboard'])->name('dashboard');
    Route::get('/search', [QuickSearchController::class, 'index'])->name('search');
    Route::get('/search/resolve', [QuickSearchController::class, 'resolve'])->name('quick-search.resolve');
    Route::post('/search/requests', [QuickSearchController::class, 'store'])->name('quick-search.request');
    Route::post('/search/requests/{fileRequest}/respond', [QuickSearchController::class, 'respond'])->name('quick-search.respond');
    Route::get('/search/log', [QuickSearchController::class, 'log'])->name('quick-search.log');
    Route::get('/search/feedback', [QuickSearchController::class, 'feedback'])->name('quick-search.feedback');
    Route::get('/search/report', [QuickSearchController::class, 'report'])->name('quick-search.report');
    Route::get('/search/report.csv', [QuickSearchController::class, 'csv'])->name('quick-search.csv');
    Route::get('/find', [FileTrackingController::class, 'find'])->name('find');
    Route::get('/find/lookup', [FileTrackingController::class, 'archiveLookup'])->name('find.lookup');
    Route::get('/log', [LogFileController::class, 'create'])->name('create');
    Route::post('/log', [LogFileController::class, 'store'])->name('store');
    Route::get('/log/file', [LogFileController::class, 'file'])->name('log.file');
    Route::get('/log/officers', [LogFileController::class, 'officers'])->name('log.officers');
    Route::get('/log/list', [LogFileController::class, 'list'])->name('log.list');
    Route::get('/lookup/files', [FileTrackingController::class, 'lookup'])->name('lookup');
    Route::get('/movement', [DepartmentMovementController::class, 'index'])->name('movement');
    Route::get('/movement/data', [DepartmentMovementController::class, 'data'])->name('movement.data');
    Route::get('/movement/scan', [DepartmentMovementController::class, 'scan'])->name('movement.scan');
    Route::post('/movement/log', [DepartmentMovementController::class, 'log'])->name('movement.log');
    Route::get('/units', [UnitMovementController::class, 'units'])->name('units');
    Route::post('/units', [UnitMovementController::class, 'storeUnit'])->name('units.store');
    Route::patch('/units/{unit}', [UnitMovementController::class, 'updateUnit'])->name('units.update');
    Route::get('/{tracker}', [FileTrackingController::class, 'show'])->name('show');
    Route::get('/{tracker}/label', [FileTrackingController::class, 'label'])->name('label');
    Route::post('/{tracker}/send', [FileTrackingController::class, 'send'])->name('send');
    Route::post('/{tracker}/receive', [FileTrackingController::class, 'receive'])->name('receive');
});

// Approvals: the Director (Arch.) and Deputy Director (Arch.) recommend, or not, each BP file.
Route::middleware('auth')->prefix('approvals')->name('bpa.')->group(function () {
    Route::get('/{desk}', [DeskController::class, 'index'])->whereIn('desk', ['director', 'deputy-director'])->name('desks.index');
    Route::post('/{desk}/{application}', [DeskController::class, 'decide'])->whereIn('desk', ['director', 'deputy-director'])->name('desks.decide');
});

// DMS (the briefing's MDC exercise), copied from KLAES: File Indexing Assistant = Shadow File Commissioning (a KLAES
// file's indexing record copied into KADCA's indexing table and flagged on KLAES), then Scan Upload and Page Typing.
Route::middleware('auth')->prefix('dms')->name('dms.')->group(function () {
    Route::get('/indexing', [IndexingController::class, 'index'])->name('indexing.index');
    Route::get('/indexing/lookup', [IndexingController::class, 'lookup'])->name('indexing.lookup');
    Route::get('/indexing/klaes/{id}', [IndexingController::class, 'klaes'])->whereNumber('id')->name('indexing.klaes');
    Route::post('/indexing', [IndexingController::class, 'store'])->name('indexing.store');
    Route::get('/indexing/{indexing}', [IndexingController::class, 'show'])->whereNumber('indexing')->name('indexing.show');
    Route::get('/scanning', [ScanUploadController::class, 'index'])->name('scanning.index');
    Route::get('/scanning/files', [ScanUploadController::class, 'files'])->name('scanning.files');
    Route::post('/scanning', [ScanUploadController::class, 'upload'])->name('scanning.upload');
    Route::get('/files/{indexing}/pages', [ScanUploadController::class, 'pages'])->whereNumber('indexing')->name('scanning.pages');
    Route::get('/scans/{scan}', [ScanUploadController::class, 'image'])->whereNumber('scan')->name('scans.image');
    Route::delete('/scans/{scan}', [ScanUploadController::class, 'destroy'])->whereNumber('scan')->name('scans.destroy');
    Route::get('/pagetyping', [PageTypingController::class, 'index'])->name('pagetyping.index');
    Route::get('/pagetyping/{indexing}', [PageTypingController::class, 'type'])->whereNumber('indexing')->name('pagetyping.type');
    Route::post('/pagetyping/scans/{scan}', [PageTypingController::class, 'save'])->whereNumber('scan')->name('pagetyping.save');
});

// Digital File Archive: File Digital Library - Doc-WARE (copied from KLAES's File Digital Archive).
Route::middleware('auth')->prefix('archive')->name('archive.')->group(function () {
    Route::get('/', [DocWareController::class, 'index'])->name('index');
    Route::get('/{indexing}', [DocWareController::class, 'show'])->whereNumber('indexing')->name('show');
});

// KLAES land files, read-only, for the file selector.
Route::middleware('auth')->prefix('klaes/files')->name('klaes.files.')->group(function () {
    Route::get('/search', [IndexedFileController::class, 'search'])->name('search');
    Route::get('/{id}', [IndexedFileController::class, 'show'])->whereNumber('id')->name('show');
});

require __DIR__.'/auth.php';
require __DIR__.'/portal.php';
require __DIR__.'/ui.php';
