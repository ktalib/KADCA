<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * File search requests, copied in concept from KLAES Quick Search: an officer searches a file and sends a
 * request for it; the Registry Monitor answers Found or Not Found (Pending / NWK). These rows drive the File
 * Search Report cards, the File Search History and the Registry Monitor Feedback, as in KLAES.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_no', 40)->unique();
            $table->foreignId('file_tracker_id')->nullable()->constrained('file_trackers');
            $table->unsignedBigInteger('bpa_application_id')->nullable()->index();
            $table->string('file_number', 150)->index();
            $table->string('file_title', 500)->nullable();
            $table->string('status_at_request', 40);     // the search outcome when the request was sent
            $table->boolean('is_blind')->default(false);  // the file was not tracked when requested
            $table->unsignedBigInteger('requester_unit_id')->nullable();
            $table->string('requester_unit_name', 150)->nullable();
            $table->string('requester_name', 300);
            $table->string('purpose', 300);
            $table->date('needed_by')->nullable();
            $table->string('status', 20)->default('awaiting'); // awaiting | found | not_found
            $table->string('not_found_type', 20)->nullable();   // pending | nwk
            $table->text('response_note')->nullable();
            $table->string('responded_by_name', 300)->nullable();
            $table->dateTime('responded_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('created_by_name', 300)->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_requests');
    }
};
