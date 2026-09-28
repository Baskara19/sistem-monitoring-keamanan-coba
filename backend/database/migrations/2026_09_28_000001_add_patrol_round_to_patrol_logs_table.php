<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menambahkan kolom patrol_round ke tabel patrol_logs.
 *
 * Kolom ini menyimpan putaran ke-berapa (1–4) satpam melakukan scan
 * dalam satu shift. Round TIDAK disimpan di schedule_details karena
 * schedule_details hanya mendefinisikan rute — bukan eksekusinya.
 *
 * Nilainya:
 *   1 → Putaran pertama  (09:00 Pagi / 16:00 Siang / 00:00 Malam)
 *   2 → Putaran kedua    (11:00 Pagi / 18:00 Siang / 02:00 Malam)
 *   3 → Putaran ketiga   (13:00 Pagi / 20:00 Siang / 04:00 Malam)
 *   4 → Putaran keempat  (14:00 Pagi / 22:00 Siang / 06:00 Malam)
 *
 * Nullable karena log lama (sebelum migrasi ini) tidak memiliki
 * informasi round — supaya data historis tidak rusak.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patrol_logs', function (Blueprint $table) {
            // Ditempatkan setelah scan_status agar urutan kolom logis:
            // schedule_detail_id → scan_time → ... → scan_status → patrol_round
            $table->unsignedTinyInteger('patrol_round')
                ->nullable()
                ->after('scan_status')
                ->comment('Putaran ke-berapa dalam shift (1–4). Null = log lama sebelum fitur round.');
        });
    }

    public function down(): void
    {
        Schema::table('patrol_logs', function (Blueprint $table) {
            $table->dropColumn('patrol_round');
        });
    }
};
