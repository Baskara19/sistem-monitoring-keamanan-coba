<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('skip_reasons', function (Blueprint $table) {
            $table->enum('review_status', [
                'pending',
                'reviewed'
            ])->default('pending');
        });
    }

    public function down(): void
    {
        Schema::table('skip_reasons', function (Blueprint $table) {
            $table->dropColumn('review_status');
        });
    }
};