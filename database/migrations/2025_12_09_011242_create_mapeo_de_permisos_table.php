<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // --- ¡CORRECCIÓN! ---
        // Si la tabla ya existe (por un intento fallido o versión vieja),
        // la borramos para poder crearla limpia con la estructura correcta.
        Schema::dropIfExists('mapeo_de_permisos');

        // Creamos la tabla en tu base de datos local (bd_tarjetas)
        Schema::create('mapeo_de_permisos', function (Blueprint $table) {
            $table->id();

            // ID de BioTime (Es más seguro enlazar por ID que por símbolo)
            // Corresponde a 'att_leavecategory.id'
            $table->unsignedBigInteger('biotime_id')->unique(); 

            // Nombre original (Solo para referencia visual tuya)
            // Ej: "102 1ER. PV2024", "Vacaciones"
            $table->string('biotime_name')->nullable(); 

            // Nuestra clasificación limpia
            // Ej: "VACACION", "INCAPACIDAD", "OTRO"
            $table->string('nuestra_categoria')->default('OTRO')->index(); 

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mapeo_de_permisos');
    }
};