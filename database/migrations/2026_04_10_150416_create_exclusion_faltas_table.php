<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crea la tabla para almacenar las nóminas que el sistema debe ignorar en faltas.
 * Primeramente Jehová Dios y Jesús Rey.
 */
return new class extends Migration
{
    // Aseguramos que corra en la conexión local
    protected $connection = 'pgsql';

    public function up(): void
    {
        Schema::create('exclusiones_faltas', function (Blueprint $table) {
            $table->id();
            // emp_code como único para evitar registros duplicados
            $table->string('emp_code', 20)->unique();
            $table->string('motivo')->nullable()->comment('Razón de la exclusión (ej. Administrativo)');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exclusiones_faltas');
    }
};