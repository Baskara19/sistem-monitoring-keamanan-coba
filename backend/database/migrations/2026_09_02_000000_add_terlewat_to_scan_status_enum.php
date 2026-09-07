<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE patrol_logs MODIFY scan_status ENUM('berhasil', 'terlambat', 'skip', 'anomali', 'terlewat') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("UPDATE patrol_logs SET scan_status = 'anomali' WHERE scan_status = 'terlewat'");
        DB::statement("ALTER TABLE patrol_logs MODIFY scan_status ENUM('berhasil', 'terlambat', 'skip', 'anomali') NOT NULL");
    }
};
