<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DRAFT, NOT RUN. Applicant portal backend (user 2026-11-23: UI first, backend next).
 *
 * Kept in database/migrations/portal/ so `php artisan migrate` and the test suite do not pick it up. When the user
 * approves the portal backend, review it, move it to database/migrations/ and run it on dev only, by itself:
 *   php artisan migrate --path=database/migrations/<file>.php
 *
 * Own tables only (kadca_ prefix added by the connection, kadca schema). No foreign keys onto KLAES dbo tables.
 * Applicants are members of the public, not KLAES staff, so they are NOT in dbo.users.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Applicant accounts (kadca_portal_applicants).
        Schema::create('portal_applicants', function (Blueprint $table) {
            $table->id();
            $table->string('username', 60)->unique();
            $table->string('name', 150);
            $table->string('phone', 20)->index();
            $table->string('email', 150)->nullable()->index();
            $table->string('password');
            $table->dateTime('phone_verified_at')->nullable();
            $table->boolean('is_demo')->default(false);       // demo login: no sign-in code (set by hand only)
            $table->boolean('must_change_password')->default(false);
            $table->boolean('is_active')->default(true);
            $table->dateTime('last_login_at')->nullable();
            $table->timestamps();
        });

        // Which applicant made an application on the portal (null = made at the KADCA counter).
        Schema::table('bpa_applications', function (Blueprint $table) {
            $table->unsignedBigInteger('portal_applicant_id')->nullable()->index();
            $table->string('submitted_via', 20)->nullable(); // counter | portal
        });

        // Construction progress: one upload per stage report, its photos with GPS + time (kadca_portal_progress_*).
        Schema::create('portal_progress_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bpa_application_id')->index();
            $table->unsignedBigInteger('portal_applicant_id')->index();
            $table->string('construction_stage', 60);
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('accuracy_m', 8, 1)->nullable();
            $table->dateTime('captured_at');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('portal_progress_photos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('portal_progress_report_id')->index();
            $table->string('original_name', 300);
            $table->string('stored_path', 500);
            $table->unsignedBigInteger('size_bytes');
            $table->dateTime('taken_at')->nullable();
            $table->timestamps();
        });

        // Site inspection requests; the Zonal Office confirms and assigns the officer (kadca_portal_inspection_bookings).
        Schema::create('portal_inspection_bookings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bpa_application_id')->index();
            $table->unsignedBigInteger('portal_applicant_id')->index();
            $table->date('slot_date');
            $table->string('slot_time', 5);
            $table->string('contact_phone', 20);
            $table->string('notes', 500)->nullable();
            $table->string('status', 20)->default('Requested'); // Requested | Confirmed | Moved | Done | Cancelled
            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->dateTime('confirmed_at')->nullable();
            $table->timestamps();
            $table->index(['slot_date', 'slot_time']);
        });

        // Notifications shown on the portal (the same text as the SMS from KLAES-CONTROL).
        Schema::create('portal_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('portal_applicant_id')->index();
            $table->unsignedBigInteger('bpa_application_id')->nullable()->index();
            $table->string('title', 200);
            $table->text('body')->nullable();
            $table->string('sms_status', 20)->nullable(); // sent | failed | skipped
            $table->dateTime('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_notifications');
        Schema::dropIfExists('portal_inspection_bookings');
        Schema::dropIfExists('portal_progress_photos');
        Schema::dropIfExists('portal_progress_reports');
        Schema::table('bpa_applications', function (Blueprint $table) {
            $table->dropIndex(['portal_applicant_id']);
            $table->dropColumn(['portal_applicant_id', 'submitted_via']);
        });
        Schema::dropIfExists('portal_applicants');
    }
};
