<?php

/*
| KADCA screens built in the UI-first phase (user 2026-11-23: "first finish the UI and frontend ... then generate the URLs,
| and next we can focus on the backend"). Required from routes/web.php. Read-only KLAES record screens read the real
| KLAES tables; forms whose backend is not built post to ui.preview (stores nothing).
*/

use App\Http\Controllers\Records\RecordsController;
use App\Http\Controllers\Ui;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    // Stub save for forms whose backend comes later, and the BP file selector.
    Route::post('/preview/{form}', [Ui\PreviewController::class, 'save'])->where('form', '[a-z0-9\-]+')->name('ui.preview');
    Route::get('/lookup/bp-files', [Ui\PreviewController::class, 'bpFiles'])->name('ui.bp-files');

    // Read-only KLAES record screens (Information Products, Land Records, DMS registers, KAMMA, GIS, CRM).
    Route::get('/information-products', [RecordsController::class, 'informationProducts'])->name('records.information-products');
    Route::get('/records/{dataset}', [RecordsController::class, 'show'])->where('dataset', '[a-z\-]+')->name('records.show');
    Route::get('/crm/customers/{type}', [RecordsController::class, 'crm'])->where('type', '[a-z\-]+')->name('crm.customers');

    // MD's View.
    Route::get('/md', [Ui\MdController::class, 'dashboard'])->name('md.dashboard');

    // Customer Relationship Management.
    Route::get('/crm', [Ui\CrmController::class, 'dashboard'])->name('crm.dashboard');
    Route::get('/crm/appointments', [Ui\CrmController::class, 'appointments'])->name('crm.appointments');
    Route::get('/crm/calendar', [Ui\CrmController::class, 'calendar'])->name('crm.calendar');

    // DMS extras (KADCA's own shadow files).
    Route::prefix('dms')->name('dms.')->group(function () {
        Route::get('/serial-grouping', [Ui\DmsExtraController::class, 'grouping'])->name('grouping');
        Route::get('/print-labels', [Ui\DmsExtraController::class, 'labels'])->name('labels');
        Route::get('/print-labels/sheet', [Ui\DmsExtraController::class, 'labelSheet'])->name('labels.sheet');
        Route::get('/sign-in-out-sheet', [Ui\DmsExtraController::class, 'signin'])->name('signin');
        Route::get('/blind-scanning', [Ui\DmsExtraController::class, 'blind'])->name('blind');
        Route::get('/quality-control', [Ui\DmsExtraController::class, 'qualityControl'])->name('qc');
    });

    // Activity Monitoring (DMS / HR / System Admin, told apart by ?scope=) and Activity Logs.
    Route::get('/activity-monitoring', [Ui\ActivityController::class, 'monitoring'])->name('activity.monitoring');
    Route::get('/system-admin/activity-logs', [Ui\ActivityController::class, 'logs'])->name('admin.activity-logs');

    // Digital File Archive: Dashboard and the Virtual File/Folder System (Folio).
    Route::get('/digital-archive', [Ui\ArchiveExtraController::class, 'dashboard'])->name('archive-dashboard');
    Route::get('/folio', [Ui\ArchiveExtraController::class, 'folio'])->name('folio.index');

    // KLAES REV-M for KADCA.
    Route::prefix('rev-m')->name('revenue.')->group(function () {
        Route::get('/', [Ui\RevenueController::class, 'dashboard'])->name('dashboard');
        Route::get('/bill-master', [Ui\RevenueController::class, 'billMaster'])->name('bill-master');
        Route::get('/betterment', [Ui\RevenueController::class, 'betterment'])->name('betterment');
        Route::get('/contravention', [Ui\RevenueController::class, 'contravention'])->name('contravention');
        Route::get('/legacy', [Ui\RevenueController::class, 'legacy'])->name('legacy');
        Route::get('/awaiting-receipts', [Ui\RevenueController::class, 'awaiting'])->name('awaiting');
        Route::get('/receipts/{mode}', [Ui\RevenueController::class, 'receipts'])->whereIn('mode', ['e-receipt', 'print'])->name('receipts');
        Route::get('/receipt/{bill}', [Ui\RevenueController::class, 'receipt'])->whereNumber('bill')->name('receipt');
        Route::get('/transaction-token-control', [Ui\RevenueController::class, 'tokens'])->name('tokens');
    });

    // KAMMA (KADCA users see KAMMA's output).
    Route::prefix('kamma')->name('kamma.')->group(function () {
        Route::get('/', [Ui\KammaController::class, 'dashboard'])->name('dashboard');
        Route::get('/title-status', [Ui\KammaController::class, 'titleStatus'])->name('title-status');
        Route::get('/planning-recommendation-approval', [Ui\KammaController::class, 'planningApproval'])->name('planning-approval');
    });

    // KADCA module screens after the Deputy Director: Zonal Office, field data, GIS, memo and conveyance.
    Route::prefix('kadca')->name('kadca.')->group(function () {
        Route::get('/', [Ui\KadcaController::class, 'dashboard'])->name('dashboard');
        Route::get('/zonal/inspection', [Ui\KadcaController::class, 'inspection'])->name('inspection');
        Route::get('/zonal/field-data', [Ui\KadcaController::class, 'fieldData'])->name('field-data');
        Route::get('/approvals/head-of-zonal-office', [Ui\KadcaController::class, 'zonalApproval'])->name('zonal-approval');
        Route::get('/gis/basemap', [Ui\KadcaController::class, 'basemap'])->name('basemap');
        Route::get('/gis/charting', [Ui\KadcaController::class, 'charting'])->name('charting');
        Route::get('/memo', [Ui\KadcaController::class, 'memo'])->name('memo');
        Route::get('/final-conveyance', [Ui\KadcaController::class, 'conveyance'])->name('conveyance');
    });

    // GIS.
    Route::prefix('gis')->name('gis.')->group(function () {
        Route::get('/survey-plan-extraction', [Ui\GisController::class, 'extraction'])->name('extraction');
        Route::get('/map', [Ui\GisController::class, 'map'])->name('map');
        Route::get('/approvals', [Ui\GisController::class, 'approvals'])->name('approvals');
        Route::get('/reports', [Ui\GisController::class, 'reports'])->name('reports');
    });

    // Legacy Systems.
    Route::get('/legacy-systems', [Ui\LegacyController::class, 'index'])->name('legacy.index');

    // HR Manager.
    Route::prefix('hr')->name('hr.')->group(function () {
        Route::get('/manual-attendance', [Ui\HrController::class, 'manualAttendance'])->name('manual-attendance');
        Route::get('/attendance-tracking', [Ui\HrController::class, 'attendance'])->name('attendance');
        Route::get('/payroll', [Ui\HrController::class, 'payroll'])->name('payroll');
    });

    // System Admin.
    Route::prefix('system-admin')->name('admin.')->group(function () {
        Route::get('/', [Ui\AdminController::class, 'dashboard'])->name('dashboard');
        Route::get('/configurable-entities', [Ui\AdminController::class, 'entities'])->name('entities');
        Route::get('/user-roles', [Ui\AdminController::class, 'roles'])->name('roles');
        Route::get('/digital-signatures', [Ui\AdminController::class, 'signatures'])->name('signatures');
        Route::get('/settings', [Ui\AdminController::class, 'settings'])->name('settings');
        Route::get('/folder-watcher', [Ui\AdminController::class, 'folderWatcher'])->name('folder-watcher');
        Route::get('/bulk-sms', [Ui\AdminController::class, 'sms'])->name('sms');
        Route::get('/verify-qrcode', [Ui\AdminController::class, 'verify'])->name('verify');
        Route::get('/mobile-apps', [Ui\AdminController::class, 'mobileApps'])->name('mobile-apps');
        Route::get('/documentation', [Ui\AdminController::class, 'documentation'])->name('documentation');
        Route::get('/documentation/file', [Ui\AdminController::class, 'documentationFile'])->name('documentation.file');
    });
});
