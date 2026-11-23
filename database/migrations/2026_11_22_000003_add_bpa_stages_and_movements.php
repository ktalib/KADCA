<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Where each BPA file is (its desk) and every move between desks, the KLAES Cadastral pattern:
 * a desk Recommends (file moves on) or does Not recommend (reason required, file goes back).
 * Additive, KADCA's own tables only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bpa_applications', function (Blueprint $table) {
            $table->string('stage', 40)->default('assessment')->index();
        });

        Schema::create('bpa_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bpa_application_id')->constrained('bpa_applications')->cascadeOnDelete();
            $table->string('from_stage', 40)->nullable();
            $table->string('to_stage', 40)->nullable();
            $table->string('action', 40); // commissioned | recommended | not_recommended | resent
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('acted_by')->nullable();
            $table->string('acted_by_name', 300)->nullable();
            $table->string('acted_by_rank', 150)->nullable();
            $table->dateTime('acted_at');
            $table->timestamps();
            $table->index(['from_stage', 'action']);
        });

        // Files already commissioned are on the Director (Arch.)'s desk.
        DB::table('bpa_applications')->whereNotNull('bp_file_no')->update(['stage' => 'director_arch', 'status' => 'With Director (Arch.)']);
    }

    public function down(): void
    {
        Schema::dropIfExists('bpa_movements');
        Schema::table('bpa_applications', function (Blueprint $table) {
            $table->dropIndex(['stage']);
            $table->dropColumn('stage');
        });
    }
};
