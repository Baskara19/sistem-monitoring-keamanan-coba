<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patrol_routes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->enum('status', ['aktif', 'nonaktif'])->default('aktif');
            $table->timestamps();
        });

        Schema::create('patrol_route_points', function (Blueprint $table) {
            $table->id();

            $table->foreignId('patrol_route_id')
                ->constrained('patrol_routes')
                ->cascadeOnDelete();

            $table->foreignId('patrol_point_id')
                ->constrained('patrol_points')
                ->cascadeOnDelete();

            $table->integer('sequence_order');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patrol_route_points');
        Schema::dropIfExists('patrol_routes');
    }
};
