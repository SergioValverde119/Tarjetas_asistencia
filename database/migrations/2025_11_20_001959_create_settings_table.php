<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // Ej: 'limite_faltas'
            $table->string('value')->nullable(); // Ej: '3'
            $table->timestamps();
        });

        // Insertamos el valor por defecto de una vez
        DB::table('settings')->insert([
            'key' => 'limite_faltas',
            'value' => '3',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};