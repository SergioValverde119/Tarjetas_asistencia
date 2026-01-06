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
        Schema::create('historial_descargas', function (Blueprint $table) {
            $table->id();
            
            // Relación con el usuario que descarga (Laravel User)
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            
            // Periodo de la tarjeta descargada
            $table->integer('month');
            $table->integer('year');
            
            // Datos de auditoría
            $table->timestamp('downloaded_at')->useCurrent(); // Fecha y hora de descarga
            $table->string('ip_address', 45)->nullable(); // IP desde donde descargó
            
            $table->timestamps();

            // Índice compuesto para búsquedas rápidas y para el updateOrInsert
            // Asegura que solo haya un registro por usuario/mes/año (actualizando la fecha si vuelve a descargar)
            $table->unique(['user_id', 'month', 'year'], 'user_card_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('historial_descargas');
    }
};