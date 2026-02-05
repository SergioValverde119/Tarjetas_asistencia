<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta la migración.
     * Esta tabla almacenará el historial de CREACION, EDICION y ELIMINACION.
     */
    public function up(): void
    {
        Schema::create('log_modificaciones_incidencias', function (Blueprint $table) {
            $table->id();

            // Usuario que realizó la acción
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->onDelete('cascade');

            // Tipo de acción: CREACION, EDICION, ELIMINACION
            $table->string('tipo_accion', 20);

            // ID de la incidencia (abstractexception_ptr_id)
            $table->integer('incidencia_id')->index();

            // Datos previos (null si es CREACION)
            $table->json('valores_anteriores')->nullable();

            // Datos finales (null si es ELIMINACION)
            $table->json('valores_nuevos')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_modificaciones_incidencias');
    }
};