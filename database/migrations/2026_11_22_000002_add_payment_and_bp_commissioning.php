<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Architecture stage: confirming the application bill was paid, then commissioning the BP file
 * (BP/[Year]/[Serial], briefing 2026-11-22). Additive columns on KADCA's own tables only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->string('receipt_no', 100)->nullable();
            $table->date('paid_on')->nullable();
            $table->decimal('amount_paid', 14, 2)->nullable();
            $table->string('payment_channel', 60)->nullable();
            $table->string('payment_channel_other', 100)->nullable();
            $table->unsignedBigInteger('payment_recorded_by')->nullable();
            $table->string('payment_recorded_by_name', 300)->nullable();
            $table->dateTime('payment_recorded_at')->nullable();
        });

        Schema::table('bpa_applications', function (Blueprint $table) {
            $table->string('bp_file_no', 40)->nullable();
            $table->dateTime('commissioned_at')->nullable();
            $table->unsignedBigInteger('commissioned_by')->nullable();
            $table->string('commissioned_by_name', 300)->nullable();
        });

        // One BP number per file. Filtered, because SQL Server lets a plain unique index hold only one NULL.
        $table = DB::getTablePrefix().'bpa_applications';
        DB::statement("CREATE UNIQUE INDEX {$table}_bp_file_no_unique ON {$table} (bp_file_no) WHERE bp_file_no IS NOT NULL");

        // A receipt number is used once.
        $bills = DB::getTablePrefix().'bills';
        DB::statement("CREATE UNIQUE INDEX {$bills}_receipt_no_unique ON {$bills} (receipt_no) WHERE receipt_no IS NOT NULL");
    }

    public function down(): void
    {
        $table = DB::getTablePrefix().'bpa_applications';
        $bills = DB::getTablePrefix().'bills';
        Schema::table('bpa_applications', fn (Blueprint $t) => $t->dropIndex("{$table}_bp_file_no_unique"));
        Schema::table('bills', fn (Blueprint $t) => $t->dropIndex("{$bills}_receipt_no_unique"));
        Schema::table('bpa_applications', fn (Blueprint $t) => $t->dropColumn(['bp_file_no', 'commissioned_at', 'commissioned_by', 'commissioned_by_name']));
        Schema::table('bills', fn (Blueprint $t) => $t->dropColumn([
            'receipt_no', 'paid_on', 'amount_paid', 'payment_channel', 'payment_channel_other',
            'payment_recorded_by', 'payment_recorded_by_name', 'payment_recorded_at',
        ]));
    }
};
