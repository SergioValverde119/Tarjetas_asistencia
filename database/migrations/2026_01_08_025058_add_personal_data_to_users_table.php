<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Campos de Nombre Separado
            // CAMBIO: 'nombre' en lugar de 'nombres'
            $table->string('nombre')->nullable()->after('name');
            $table->string('paterno')->nullable()->after('nombre');
            $table->string('materno')->nullable()->after('paterno');
            
            // Campos Fiscales/Identidad
            $table->string('rfc', 13)->nullable()->unique()->after('materno'); 
            $table->string('curp', 18)->nullable()->unique()->after('rfc');    
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['nombre', 'paterno', 'materno', 'rfc', 'curp']);
        });
    }
};