<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * KADCA Assessment Stage: Building Plan Approval applications, their uploaded plans, the application
 * bill, and the yearly serial counters. All in KADCA's own schema with the kadca_ prefix (added by the
 * connection). Property details are a snapshot of the KLAES indexing record the file was picked from;
 * there is no foreign key onto KLAES tables (the login has no REFERENCES on dbo, and it would block KLAES).
 */
return new class extends Migration
{
    public function up(): void
    {
        // One row per counter per year, e.g. ('bpa_application', 2026). Numbers restart every 1 January.
        Schema::create('serials', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_value')->default(0);
            $table->timestamps();
            $table->unique(['name', 'year']);
        });

        Schema::create('bpa_applications', function (Blueprint $table) {
            $table->id();
            $table->string('application_no', 40)->unique();
            $table->string('status', 30)->default('Submitted');

            // The KLAES land file (selected, never typed) and its details at the time of application.
            $table->unsignedBigInteger('klaes_file_indexing_id')->index();
            $table->string('file_number', 100)->index();
            $table->string('file_title', 500)->nullable();
            $table->string('plot_no', 100)->nullable();
            $table->string('district', 200)->nullable();
            $table->string('lga', 100)->nullable();
            $table->string('location', 500)->nullable();
            $table->string('plot_size', 50)->nullable();
            $table->string('file_land_use', 100)->nullable();
            $table->string('file_land_use_code', 10)->nullable();
            $table->string('title_status', 100)->nullable();

            // Land-use check: proposed building use against the land file. A mismatch is flagged, not blocked.
            $table->string('proposed_use', 60);
            $table->string('proposed_use_other', 200)->nullable();
            $table->string('land_use_check', 20); // Match | Mismatch | Not checked
            $table->string('land_use_note', 500)->nullable();

            // Developer and representative (application form, page 1).
            $table->string('developer_name', 300);
            $table->string('cadastral_zone', 100)->nullable();
            $table->string('cadastral_zone_plot_no', 150)->nullable();
            $table->string('street_name', 200)->nullable();
            $table->string('dwelling_plot_floor', 100)->nullable();
            $table->string('dwelling_unit', 100)->nullable();
            $table->string('contact_address', 500)->nullable();
            $table->string('developer_phone', 40)->nullable();
            $table->string('developer_email', 150)->nullable();
            $table->string('representative_phone', 40)->nullable();
            $table->string('representative_email', 150)->nullable();

            $table->string('submission_type', 30); // New | Re-submission | Revalidation | Other
            $table->string('submission_type_other', 200)->nullable();
            $table->text('documents_submitted')->nullable(); // JSON list
            $table->string('documents_other', 300)->nullable();
            $table->text('drawings_submitted')->nullable(); // JSON list
            $table->string('plan_received_as', 40); // how the building plan came in

            $table->unsignedSmallInteger('number_of_plots')->default(1);
            $table->string('submitted_by_name', 300)->nullable();
            $table->date('submitted_on')->nullable();

            // Receiving officer: the signed-in KLAES user, with name and rank as they were then.
            $table->unsignedBigInteger('received_by')->nullable();
            $table->string('received_by_name', 300)->nullable();
            $table->string('received_by_rank', 150)->nullable();

            $table->timestamps();
        });

        Schema::create('bpa_application_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bpa_application_id')->constrained('bpa_applications')->cascadeOnDelete();
            $table->string('kind', 30); // plan | cad
            $table->string('original_name', 300);
            $table->string('stored_path', 500);
            $table->string('extension', 10);
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();
        });

        // KADCA bills. Kept here until the user approves writing them into KLAES `billing` (source = KADCA).
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->string('bill_no', 40)->unique();
            $table->foreignId('bpa_application_id')->constrained('bpa_applications')->cascadeOnDelete();
            $table->string('bill_type', 60); // BPA Application Fee
            $table->decimal('rate', 14, 2);
            $table->unsignedSmallInteger('quantity');
            $table->decimal('amount', 14, 2);
            $table->string('status', 20)->default('Unpaid');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bills');
        Schema::dropIfExists('bpa_application_files');
        Schema::dropIfExists('bpa_applications');
        Schema::dropIfExists('serials');
    }
};
