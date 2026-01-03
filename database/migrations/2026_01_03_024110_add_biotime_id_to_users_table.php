<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Agregamos la columna para el ID interno de BioTime (personnel_employee.id)
            // Es la forma más segura de enlazar, mejor que el emp_code.
            $table->unsignedBigInteger('biotime_id')->nullable()->unique()->after('emp_code');
            
            // Opcional: Index para búsquedas rápidas
            $table->index('biotime_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('biotime_id');
        });
    }
};