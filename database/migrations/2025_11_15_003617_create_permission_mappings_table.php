<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {

        Schema::create('mapeo_de_permisos', function (Blueprint $table) {
            $table->id();

            $table->string('biotime_report_symbol')->unique(); 
            
            $table->string('biotime_category_name'); 

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