<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabel patrol_handovers untuk mencatat permintaan dan persetujuan handover per titik
        Schema::create('patrol_handovers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_satpam_id')->constrained('satpams')->onDelete('cascade');
            $table->foreignId('to_satpam_id')->constrained('satpams')->onDelete('cascade');
            $table->foreignId('schedule_detail_id')->constrained('schedule_details')->onDelete('cascade');
            $table->foreignId('patrol_point_id')->constrained('patrol_points')->onDelete('cascade');
            $table->unsignedTinyInteger('patrol_round')->comment('Putaran ke 1–4');
            $table->date('handover_date');
            $table->string('reason', 255)->comment('Alasan handover');
            $table->enum('status', ['pending', 'accepted', 'rejected', 'cancelled'])->default('pending');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index(['to_satpam_id', 'status', 'handover_date']);
            $table->index(['from_satpam_id', 'handover_date']);
            $table->index(['schedule_detail_id', 'patrol_round']);
        });

        // 2. Tambah relasi ke patrol_logs agar jelas siapa pelaksana dan siapa yang diwakili
        Schema::table('patrol_logs', function (Blueprint $table) {
            $table->foreignId('delegated_from_satpam_id')
                ->nullable()
                ->after('satpam_id')
                ->constrained('satpams')
                ->nullOnDelete();

            $table->foreignId('patrol_handover_id')
                ->nullable()
                ->after('delegated_from_satpam_id')
                ->constrained('patrol_handovers')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('patrol_logs', function (Blueprint $table) {
            $table->dropForeign(['patrol_handover_id']);
            $table->dropForeign(['delegated_from_satpam_id']);
            $table->dropColumn(['patrol_handover_id', 'delegated_from_satpam_id']);
        });

        Schema::dropIfExists('patrol_handovers');
    }
};
