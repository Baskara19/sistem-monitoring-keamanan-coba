<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nipkwt', 30)->nullable()->unique()->after('email');
            $table->unsignedInteger('tim')->nullable()->after('role');
        });

        // Katim login-nya sama seperti satpam (ke dashboard satpam), cuma
        // dibedakan lewat nomor tim (kolom `tim`).
        DB::statement("ALTER TABLE users MODIFY role ENUM('admin', 'satpam', 'supervisor', 'katim') NOT NULL DEFAULT 'satpam'");
    }

    public function down(): void
    {
        DB::statement("UPDATE users SET role = 'satpam' WHERE role = 'katim'");
        DB::statement("ALTER TABLE users MODIFY role ENUM('admin', 'satpam', 'supervisor') NOT NULL DEFAULT 'satpam'");

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['nipkwt', 'tim']);
        });
    }
};
