<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * KADCA File Tracking, copied in concept from KLAES (file_tracker + file_movement_units): every physical file or
 * mail gets a tracking ID and QR code, sits in a unit, and moves between units by Send / Receive. KADCA's own
 * tables, so it never mixes with KLAES land-file tracking (briefing 2026-11-22).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Units files move between. No KADCA department list yet (user 2026-11-22), so the known desks are seeded
        // and the list is editable. `stage` links a unit to a BPA desk so desk decisions move the physical file too.
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->string('stage', 40)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('file_trackers', function (Blueprint $table) {
            $table->id();
            $table->string('tracking_id', 30)->unique();
            $table->string('kind', 10); // file | mail
            $table->unsignedBigInteger('bpa_application_id')->nullable();
            $table->string('reference', 150);           // BP file no. / application no. / mail reference
            $table->string('title', 500)->nullable();   // developer / mail subject
            $table->string('mail_from', 300)->nullable();
            $table->string('priority', 20)->default('Normal');
            $table->string('status', 20);               // in_unit | in_transit
            $table->foreignId('current_unit_id')->constrained('units');
            $table->foreignId('to_unit_id')->nullable()->constrained('units');
            $table->date('due_on')->nullable();
            $table->string('shelf_location', 150)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('created_by_name', 300)->nullable();
            $table->timestamps();
            $table->index(['current_unit_id', 'status']);
        });

        $unitsTable = DB::getTablePrefix().'units';
        DB::statement("CREATE UNIQUE INDEX {$unitsTable}_stage_unique ON {$unitsTable} (stage) WHERE stage IS NOT NULL");

        $trackers = DB::getTablePrefix().'file_trackers';
        // One tracker per BPA file. Filtered: SQL Server lets a plain unique index hold only one NULL.
        DB::statement("CREATE UNIQUE INDEX {$trackers}_bpa_application_id_unique ON {$trackers} (bpa_application_id) WHERE bpa_application_id IS NOT NULL");

        Schema::create('file_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_tracker_id')->constrained('file_trackers')->cascadeOnDelete();
            $table->string('action', 20); // logged | sent | received
            $table->unsignedBigInteger('from_unit_id')->nullable();
            $table->unsignedBigInteger('to_unit_id')->nullable();
            $table->string('purpose', 300)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('acted_by')->nullable();
            $table->string('acted_by_name', 300)->nullable();
            $table->dateTime('acted_at');
            $table->timestamps();
        });

        $now = now();
        $units = [
            ['Registry', null], ['Accounts', null], ['Architecture', 'architecture'], ['Director (Arch.)', 'director_arch'],
            ['Deputy Director (Arch.)', 'dd_arch'], ['Zonal Office', 'zonal_office'], ["MD's Office", null], ['Archive', null],
        ];
        foreach ($units as $i => [$name, $stage]) {
            DB::table('units')->insert(['name' => $name, 'stage' => $stage, 'is_active' => true, 'sort_order' => ($i + 1) * 10, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('file_movements');
        Schema::dropIfExists('file_trackers');
        Schema::dropIfExists('units');
    }
};
