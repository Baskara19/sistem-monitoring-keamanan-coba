<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_details', function (Blueprint $table) {
            $table->id();

            $table->foreignId('schedule_id')
                ->constrained('schedules')
                ->cascadeOnDelete();

            $table->foreignId('satpam_id')
                ->constrained('satpams')
                ->cascadeOnDelete();

            $table->foreignId('patrol_point_id')
                ->constrained('patrol_points')
                ->cascadeOnDelete();

            $table->time('shift_start');
            $table->time('shift_end');

            $table->integer('sequence_order');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_details');
    }
};