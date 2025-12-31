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
        Schema::table('users', function (Blueprint $table) {
            // 1. Agregamos la columna para el RFC (username)
            // La ponemos después del 'name' para que se vea ordenado
            $table->string('username')->unique()->after('name');

            // 2. Hacemos que el email sea opcional (nullable)
            // Esto permite crear usuarios con puro RFC si no tienen correo
            $table->string('email')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Revertir los cambios
            $table->dropColumn('username');
            $table->string('email')->nullable(false)->change(); // Volver a obligatorio
        });
    }
};