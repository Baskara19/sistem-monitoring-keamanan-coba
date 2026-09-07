<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("UPDATE patrol_logs SET scan_status = 'berhasil' WHERE scan_status = 'valid'");
        DB::statement("UPDATE patrol_logs SET scan_status = 'anomali' WHERE scan_status = 'invalid'");

        DB::statement("ALTER TABLE patrol_logs MODIFY scan_status ENUM('berhasil', 'terlambat', 'skip', 'anomali') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("UPDATE patrol_logs SET scan_status = 'valid' WHERE scan_status = 'berhasil'");
        DB::statement("UPDATE patrol_logs SET scan_status = 'invalid' WHERE scan_status IN ('anomali', 'terlambat')");

        DB::statement("ALTER TABLE patrol_logs MODIFY scan_status ENUM('valid', 'skip', 'invalid') NOT NULL");
    }
};
