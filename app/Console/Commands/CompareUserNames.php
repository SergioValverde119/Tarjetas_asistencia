<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CompareUserNames extends Command
{
    /**
     * El nombre y la firma del comando en la consola.
     */
    protected $signature = 'app:comparar-nombres-biotime';

    /**
     * Descripción del comando.
     */
    protected $description = 'Muestra una tabla comparativa entre el nombre local y el de BioTime para verificar coincidencias.';

    public function handle()
    {
        $users = User::all();
        $total = $users->count();

        $this->info("Analizando $total usuarios para comparación...");
        
        $headers = ['ID Local', 'Nombre SISTEMA', 'Nombre BIOTIME', 'Vínculo (ID Bio)', 'Estado'];
        $rows = [];

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($users as $user) {
            $bioEmployee = null;
            $metodoBusqueda = '';

            // 1. Intentamos buscar por el ID interno seguro (biotime_id)
            if ($user->biotime_id) {
                $bioEmployee = DB::connection('pgsql_biotime')
                    ->table('personnel_employee')
                    ->where('id', $user->biotime_id)
                    ->select('id', 'first_name', 'last_name', 'emp_code')
                    ->first();
                $metodoBusqueda = 'ID Interno';
            } 
            // 2. Si no, intentamos por emp_code (Plan B)
            elseif ($user->emp_code) {
                $bioEmployee = DB::connection('pgsql_biotime')
                    ->table('personnel_employee')
                    ->where('emp_code', $user->emp_code)
                    ->select('id', 'first_name', 'last_name', 'emp_code')
                    ->first();
                $metodoBusqueda = 'Código Emp';
            }

            // Preparar datos para la tabla
            $nombreLocal = $user->name;
            $nombreBio = '--- NO ENCONTRADO ---';
            $bioIdStr = 'N/A';
            $estado = '❌ SIN DATOS';

            if ($bioEmployee) {
                // BioTime suele tener nombre y apellido separados
                $nombreBio = trim($bioEmployee->first_name . ' ' . $bioEmployee->last_name);
                $bioIdStr = $bioEmployee->id . " ($metodoBusqueda)";

                // Normalización básica (Mayúsculas, acentos, espacios)
                $localNorm = $this->normalizar($nombreLocal);
                $bioNorm = $this->normalizar($nombreBio);

                // Comparación 1: Directa
                if ($localNorm === $bioNorm) {
                    $estado = '✅ EXACTO';
                } 
                // Comparación 2: Por palabras ordenadas (Ignora orden Apellido-Nombre)
                elseif ($this->ordenarPalabras($localNorm) === $this->ordenarPalabras($bioNorm)) {
                    $estado = '✅ EXACTO (Orden Invertido)';
                }
                else {
                    // Calculamos similitud para ver si es un error de dedo
                    $porcentaje = 0;
                    similar_text($localNorm, $bioNorm, $porcentaje);
                    
                    if ($porcentaje > 80) {
                        $estado = '⚠️ SIMILAR (' . round($porcentaje) . '%)';
                    } else {
                        $estado = '❗ DIFERENTE';
                    }
                }
            }

            // Agregamos la fila a la tabla visual con colores
            if (str_contains($estado, '✅')) {
                $rows[] = [$user->id, $nombreLocal, $nombreBio, $bioIdStr, "<info>$estado</info>"];
            } elseif (str_contains($estado, '❌')) {
                $rows[] = [$user->id, $nombreLocal, $nombreBio, $bioIdStr, "<error>$estado</error>"];
            } else {
                $rows[] = [$user->id, $nombreLocal, $nombreBio, $bioIdStr, "<comment>$estado</comment>"];
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->table($headers, $rows);
        $this->info("Comparación terminada.");
    }

    /**
     * Función auxiliar para limpiar strings antes de comparar.
     * Convierte a mayúsculas y quita acentos.
     */
    private function normalizar($string)
    {
        $string = mb_strtoupper($string, 'UTF-8');
        // Reemplazar múltiples espacios por uno solo
        $string = preg_replace('/\s+/', ' ', $string);
        // Quitar acentos básicos para facilitar la comparación
        $string = str_replace(
            ['Á', 'É', 'Í', 'Ó', 'Ú', 'Ñ'],
            ['A', 'E', 'I', 'O', 'U', 'N'],
            $string
        );
        return trim($string);
    }

    /**
     * Ordena las palabras de una frase alfabéticamente.
     * Ejemplo: "LOPEZ PEREZ JUAN" -> "JUAN LOPEZ PEREZ"
     */
    private function ordenarPalabras($string)
    {
        $palabras = explode(' ', $string);
        sort($palabras);
        return implode(' ', $palabras);
    }
}