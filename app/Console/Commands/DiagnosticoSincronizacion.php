<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DiagnosticoSincronizacion extends Command
{
    protected $signature = 'incidencias:diagnostico';
    protected $description = 'Compara los registros de permisos entre las dos bases de datos';

    public function handle()
    {
        $this->info("🔍 Iniciando diagnóstico de sincronización...");

        try {
            $totalRespaldo = DB::connection('pgsql_biotime')->table('att_leave')->count();
            $totalOriginal = DB::connection('pgsql_original')->table('att_leave')->count();

            $this->info("📊 Total en Respaldo: $totalRespaldo");
            $this->info("📊 Total en Original: $totalOriginal");

            if ($totalRespaldo > $totalOriginal) {
                $this->warn("⚠️ Hay " . ($totalRespaldo - $totalOriginal) . " registros en el Respaldo que NO están en la Original.");
                
                // Vamos a listar los IDs que faltan
                $idsRespaldo = DB::connection('pgsql_biotime')->table('att_leave')->pluck('abstractexception_ptr_id')->toArray();
                $idsOriginal = DB::connection('pgsql_original')->table('att_leave')->pluck('abstractexception_ptr_id')->toArray();
                
                $faltantes = array_diff($idsRespaldo, $idsOriginal);
                
                $this->info("IDs faltantes (Muestra):");
                $this->table(['ID Folio'], array_map(function($id) { return [$id]; }, array_slice($faltantes, 0, 10)));
                
                if (count($faltantes) > 10) {
                     $this->info("... y " . (count($faltantes) - 10) . " más.");
                }

            } elseif ($totalOriginal > $totalRespaldo) {
                 $this->warn("⚠️ Hay más registros en la Original que en el Respaldo.");
            } else {
                $this->info("✅ Ambas bases de datos tienen la misma cantidad de registros.");
            }

        } catch (\Exception $e) {
            $this->error("❌ Error de conexión: " . $e->getMessage());
        }
    }
}