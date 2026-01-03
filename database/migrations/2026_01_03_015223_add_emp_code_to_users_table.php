<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // CORRECTO: Usamos 'unsignedBigInteger' porque en BioTime el ID es un número (bigint).
            // Lo ponemos después de 'username' (RFC) para mantener el orden visual.
            $table->unsignedBigInteger('emp_code')->nullable()->unique()->after('username');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('emp_code');
        });
    }
};