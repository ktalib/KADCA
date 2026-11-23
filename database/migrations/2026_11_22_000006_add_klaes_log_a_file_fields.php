<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The fields KLAES's Log a File (Create File Tracker) records, so the copied screen can store them:
 * priority (Low / Medium / High), request purpose, timeline and expected return date, number of pages,
 * the officer holding the file (a KLAES user, who accepts it), and a log ID per entry. Additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('file_trackers', function (Blueprint $table) {
            $table->unsignedBigInteger('holder_user_id')->nullable()->index();
            $table->string('holder_name', 300)->nullable();
            $table->unsignedInteger('num_pages')->nullable();
        });

        Schema::table('file_movements', function (Blueprint $table) {
            $table->string('log_id', 40)->nullable()->index();
            $table->string('priority', 20)->nullable();
            $table->unsignedSmallInteger('timeline_days')->nullable();
            $table->date('expected_return_on')->nullable();
            $table->unsignedInteger('num_pages')->nullable();
            $table->unsignedBigInteger('holder_user_id')->nullable();
            $table->string('holder_name', 300)->nullable();
        });

        // KLAES priorities: Normal → Medium, Urgent → High.
        DB::table('file_trackers')->where('priority', 'Normal')->update(['priority' => 'Medium']);
        DB::table('file_trackers')->where('priority', 'Urgent')->update(['priority' => 'High']);
    }

    public function down(): void
    {
        Schema::table('file_movements', fn (Blueprint $t) => $t->dropColumn(['log_id', 'priority', 'timeline_days', 'expected_return_on', 'num_pages', 'holder_user_id', 'holder_name']));
        Schema::table('file_trackers', function (Blueprint $t) {
            $t->dropIndex(['holder_user_id']);
            $t->dropColumn(['holder_user_id', 'holder_name', 'num_pages']);
        });
    }
};
