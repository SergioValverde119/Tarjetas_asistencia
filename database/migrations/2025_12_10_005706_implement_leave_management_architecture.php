<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. CREAR EL CATÁLOGO (leave_categories)
        Schema::create('leave_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Ej. "Vacaciones"
            $table->string('color')->default('blue'); // Ej. "blue", "red"
            $table->boolean('is_paid')->default(true); // Se paga o se descuenta
            $table->timestamps();
        });

        // 2. INSERTAR DATOS BASE (Tus categorías limpias)
        // Insertamos los IDs fijos para poder relacionarlos fácilmente
        DB::table('leave_categories')->insert([
            ['id' => 1, 'name' => 'Vacaciones', 'color' => 'blue', 'is_paid' => true],
            ['id' => 2, 'name' => 'Incapacidad', 'color' => 'red', 'is_paid' => true],
            ['id' => 3, 'name' => 'Permiso con Goce', 'color' => 'green', 'is_paid' => true],
            ['id' => 4, 'name' => 'Permiso sin Goce', 'color' => 'yellow', 'is_paid' => false],
            ['id' => 5, 'name' => 'Maternidad', 'color' => 'pink', 'is_paid' => true],
            ['id' => 6, 'name' => 'Paternidad', 'color' => 'indigo', 'is_paid' => true],
            ['id' => 7, 'name' => 'Falta Justificada', 'color' => 'gray', 'is_paid' => false],
            ['id' => 8, 'name' => 'Otro', 'color' => 'gray', 'is_paid' => true],
        ]);

        // 3. CREAR EL TRADUCTOR (leave_mappings)
        // Esta tabla reemplazará a 'mapeo_de_permisos'
        Schema::create('leave_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('biotime_leave_id')->unique(); // ID original de BioTime
            
            // Relación con nuestra nueva tabla limpia
            $table->foreignId('leave_category_id')->constrained('leave_categories')->onDelete('cascade');
            
            $table->timestamps();
        });

        // 4. MIGRAR DATOS ANTIGUOS (Si existen)
        // Intentamos rescatar lo que ya habías mapeado en 'mapeo_de_permisos'
        if (Schema::hasTable('mapeo_de_permisos')) {
            $oldMappings = DB::table('mapeo_de_permisos')->get();
            
            foreach ($oldMappings as $old) {
                // Buscamos a qué ID nuevo corresponde el texto viejo
                $newCategoryId = match($old->nuestra_categoria) {
                    'VACACION' => 1,
                    'INCAPACIDAD' => 2,
                    'PERMISO_CON_GOCE' => 3,
                    'PERMISO_SIN_GOCE' => 4,
                    'PERMISO_MATERNIDAD' => 5,
                    'PERMISO_PATERNIDAD' => 6,
                    'FALTA_JUSTIFICADA' => 7,
                    default => 8 // Otro
                };

                // Insertamos en la nueva tabla
                DB::table('leave_mappings')->insertOrIgnore([
                    'biotime_leave_id' => $old->biotime_id,
                    'leave_category_id' => $newCategoryId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Opcional: Borrar la tabla vieja ahora que ya migramos
            // Schema::drop('mapeo_de_permisos'); 
        }

        // 5. CREAR LAS POLÍTICAS (leave_policies)
        Schema::create('leave_policies', function (Blueprint $table) {
            $table->id();
            
            // Relación: Una política pertenece a una Categoría (Ej. Reglas para "Vacaciones")
            $table->foreignId('leave_category_id')->constrained('leave_categories')->onDelete('cascade');
            
            // Relación: Una política aplica a una Nómina (Área ID de BioTime)
            // Si es NULL, es una regla global para todos
            $table->integer('area_id')->nullable()->index(); 
            
            // Las Reglas
            $table->integer('limit_amount'); // Ej. 10
            $table->string('frequency')->default('YEARLY'); // ANUAL, SEMESTRAL...
            
            $table->timestamps();

            // Evitar duplicados: Solo una regla por (Categoría + Nómina + Frecuencia)
            $table->unique(['leave_category_id', 'area_id', 'frequency'], 'policy_unique_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_policies');
        Schema::dropIfExists('leave_mappings');
        Schema::dropIfExists('leave_categories');
    }
};