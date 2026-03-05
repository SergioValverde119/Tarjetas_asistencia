<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\LogModificacionIncidencia;
use Exception;
use Carbon\Carbon;

/**
 * Comando Maestro de Sincronización de Incidencias
 * Versión de Integridad Total con Transacciones y Vista Detallada.
 * Primeramente Jehová Dios y Jesús Rey.
 */
class SincronizarJustificacionesBitacora extends Command
{
    protected $signature = 'incidencias:sincronizacion-total 
                            {--adjust-folios= : Fase 0: Reiniciar secuencias al ID máximo hasta una fecha}
                            {--sync-deletes : Fase 1: Replicar eliminaciones de la bitácora}
                            {--sync-edits   : Fase 2: Replicar ediciones de la bitácora}
                            {--sync-categories : Fase 3A: Sincronizar catálogo de tipos de permiso}
                            {--sync-new     : Fase 3B: Rescatar registros nuevos (INTERACTIVO)}
                            {--purge-future= : Fase 4: Borrar registros futuros en Original}
                            {--all          : Ejecutar todas las fases}
                            {--dry-run      : Modo simulación}';

    protected $description = 'Sincronización fidedigna con transacciones e interfaz detallada.';

    public function handle()
    {
        $this->info("╔══════════════════════════════════════════════════════════════╗");
        $this->info("║      MOTOR DE OPERACIONES QUIRÚRGICAS DE ASISTENCIA          ║");
        $this->info("║             Primeramente Jehová Dios y Jesús Rey             ║");
        $this->info("╚══════════════════════════════════════════════════════════════╝");

        if ($this->option('dry-run')) {
            $this->warn("⚠️  MODO SIMULACIÓN: No se aplicarán cambios reales.");
        }

        $hasAdjustFolios = $this->input->hasParameterOption('--adjust-folios');
        $hasPurgeFuture  = $this->input->hasParameterOption('--purge-future');

        $runAll = $this->option('all') || (
            !$hasAdjustFolios && 
            !$this->option('sync-deletes') && 
            !$this->option('sync-edits') && 
            !$this->option('sync-categories') && 
            !$this->option('sync-new') &&
            !$hasPurgeFuture
        );

        try {
            if ($hasPurgeFuture) {
                 $this->purgarRegistrosFuturos($this->option('purge-future'));
            }

            if ($runAll || $hasAdjustFolios) {
                $this->ajustarSecuencias($this->option('adjust-folios'));
            }

            if ($runAll || $this->option('sync-deletes')) {
                $this->procesarEliminaciones();
            }

            if ($runAll || $this->option('sync-edits')) {
                $this->procesarEdiciones();
            }

            if ($runAll || $this->option('sync-categories') || $this->option('sync-new')) {
                $this->sincronizarCategorias();
            }

            if ($runAll || $this->option('sync-new')) {
                $this->rescatarRegistrosInexistentes();
            }

            $this->info("\n✨ ¡Sincronización finalizada correctamente! ✨");

        } catch (Exception $e) {
            $this->error("\n❌ ERROR CRÍTICO: " . $e->getMessage());
        }
    }

    private function purgarRegistrosFuturos($fechaLimiteStr)
    {
        $this->warn("\n[FASE EXTRA] 🧹 Limpiando registros posteriores a $fechaLimiteStr...");
        $fechaLimite = Carbon::parse($fechaLimiteStr)->endOfDay();

        $futuros = DB::connection('pgsql_original')->table('att_leave')->where('apply_time', '>', $fechaLimite)->get();

        if ($futuros->isEmpty()) return;

        if ($this->option('dry-run')) {
            $this->info("   👀 SIMULACIÓN: Se borrarían " . count($futuros) . " registros.");
            return;
        }

        // Usamos transacción para asegurar que se borren de todas las tablas o de ninguna
        DB::connection('pgsql_original')->transaction(function() use ($futuros) {
            foreach ($futuros as $row) {
                $id = $row->abstractexception_ptr_id;
                DB::connection('pgsql_original')->table('att_payloadexception')->where('item_id', (string)$id)->delete();
                DB::connection('pgsql_original')->table('att_leave')->where('abstractexception_ptr_id', $id)->delete();
                DB::connection('pgsql_original')->table('workflow_abstractexception')->where('id', $id)->delete();
            }
        });
        
        $this->info("   ✅ " . count($futuros) . " registros eliminados físicamente.");
    }

    private function ajustarSecuencias($fechaLimiteStr = null)
    {
        $this->warn("\n[FASE 0] ⚙️  Ajustando folios...");
        $query = DB::connection('pgsql_biotime')->table('att_leave');
        if ($fechaLimiteStr) {
            $query->where('apply_time', '<=', Carbon::parse($fechaLimiteStr)->endOfDay());
        }
        $maxId = $query->max('abstractexception_ptr_id');
        
        if ($maxId && !$this->option('dry-run')) {
            DB::connection('pgsql_original')->statement("SELECT setval('public.workflow_abstractexception_id_seq', ?, true)", [$maxId + 1]);
            $this->info("   ✅ Secuencia ajustada al ID #" . ($maxId + 1));
        }
    }

    private function procesarEliminaciones()
    {
        $this->warn("\n[FASE 1] 🧹 Procesando eliminaciones de bitácora...");
        $borrados = LogModificacionIncidencia::where('tipo_accion', 'ELIMINACION')->get();
        
        if ($this->option('dry-run')) {
            $this->info("   👀 SIMULACIÓN: Se replicarían " . count($borrados) . " eliminaciones.");
            return;
        }

        DB::connection('pgsql_original')->transaction(function() use ($borrados) {
            foreach ($borrados as $log) {
                $id = $log->incidencia_id;
                DB::connection('pgsql_original')->table('att_payloadexception')->where('item_id', (string)$id)->delete();
                DB::connection('pgsql_original')->table('att_leave')->where('abstractexception_ptr_id', $id)->delete();
                DB::connection('pgsql_original')->table('workflow_abstractexception')->where('id', $id)->delete();
            }
        });
        $this->info("   ✅ " . count($borrados) . " eliminaciones replicadas.");
    }

    private function procesarEdiciones()
    {
        $this->warn("\n[FASE 2] 📝 Procesando ediciones de bitácora...");
        $ediciones = LogModificacionIncidencia::where('tipo_accion', 'EDICION')->get();
        
        if ($this->option('dry-run')) {
            $this->info("   👀 SIMULACIÓN: Se replicarían " . count($ediciones) . " ediciones.");
            return;
        }

        DB::connection('pgsql_original')->transaction(function() use ($ediciones) {
            foreach ($ediciones as $log) {
                $id = $log->incidencia_id;
                $datos = $log->valores_nuevos;
                DB::connection('pgsql_original')->table('att_payloadexception')->where('item_id', (string)$id)->delete();
                DB::connection('pgsql_original')->table('att_leave')->where('abstractexception_ptr_id', $id)->update([
                    'category_id' => $datos['category_id'],
                    'start_time'  => $datos['start_time'],
                    'end_time'    => $datos['end_time'],
                    'apply_reason'=> $datos['reason']
                ]);
            }
        });
        $this->info("   ✅ " . count($ediciones) . " ediciones replicadas.");
    }

    private function sincronizarCategorias()
    {
        $this->warn("\n[FASE 3A] 🏷️  Sincronizando Catálogo de Categorías...");
        $categorias = DB::connection('pgsql_biotime')->table('att_leavecategory')->get();
        foreach($categorias as $cat) {
            $existe = DB::connection('pgsql_original')->table('att_leavecategory')->where('id', $cat->id)->exists();
            if(!$existe && !$this->option('dry-run')) {
                DB::connection('pgsql_original')->table('att_leavecategory')->insert((array)$cat);
                $this->line("   + Nueva categoría rescatada: {$cat->category_name}");
            }
        }
    }

    private function rescatarRegistrosInexistentes()
    {
        $this->warn("\n[FASE 3B] 🔎 Buscando registros nuevos para rescatar...");
        $respaldo = DB::connection('pgsql_biotime')->table('att_leave')->get();
        
        foreach ($respaldo as $row) {
            $exists = DB::connection('pgsql_original')->table('att_leave')->where('abstractexception_ptr_id', $row->abstractexception_ptr_id)->exists();
            if (!$exists) {
                
                // DETECCIÓN DE TRASLAPES DETALLADA
                $overlaps = DB::connection('pgsql_original')->table('att_leave')
                    ->where('employee_id', $row->employee_id)
                    ->where(function($q) use ($row) {
                        $q->where('start_time', '<', $row->end_time)
                          ->where('end_time', '>', $row->start_time);
                    })->get();

                if ($overlaps->isNotEmpty()) {
                    // BUSCAR NOMBRE DEL EMPLEADO PARA EL INFORME
                    $emp = DB::connection('pgsql_biotime')->table('personnel_employee')->where('id', $row->employee_id)->first();
                    $nombreEmp = $emp ? "{$emp->first_name} {$emp->last_name} (Nómina: {$emp->emp_code})" : "Empleado ID: {$row->employee_id}";

                    $this->error("\n===================================================================");
                    $this->error(" ⚠️  ALERTA DE TRASLAPE DETECTADO: {$nombreEmp}");
                    $this->error("===================================================================");

                    $this->warn("\n🔴 EN LA BASE DE DATOS ORIGINAL (Lo que ya existe):");
                    foreach ($overlaps as $over) {
                        $catOrig = DB::connection('pgsql_original')->table('att_leavecategory')->where('id', $over->category_id)->first();
                        $tipoOrig = $catOrig ? $catOrig->category_name : "ID: {$over->category_id}";
                        $this->line("    Folio: #{$over->abstractexception_ptr_id} | Tipo: {$tipoOrig}");
                        $this->line("    Fechas: Del {$over->start_time} al {$over->end_time}");
                        $this->line("    Motivo: {$over->apply_reason}");
                    }

                    $catNew = DB::connection('pgsql_biotime')->table('att_leavecategory')->where('id', $row->category_id)->first();
                    $tipoNew = $catNew ? $catNew->category_name : "ID: {$row->category_id}";
                    
                    $this->info("\n🟢 EN LA BASE DE RESPALDO (Lo que intentas subir):");
                    $this->line("    Folio: #{$row->abstractexception_ptr_id} | Tipo: {$tipoNew}");
                    $this->line("    Fechas: Del {$row->start_time} al {$row->end_time}");
                    $this->line("    Motivo: {$row->apply_reason}\n");

                    $opcion = $this->choice('¿Qué deseas hacer?', ['Saltar este nuevo', 'Reemplazar el original por este nuevo', 'Conservar ambos (Encimarlos)'], 0);
                    
                    if ($opcion === 'Saltar este nuevo') continue;
                    
                    if ($opcion === 'Reemplazar el original por este nuevo') {
                        // Acción transaccional: Borrar viejos e insertar nuevo
                        DB::connection('pgsql_original')->transaction(function() use ($overlaps, $row) {
                            foreach ($overlaps as $o) {
                                $oid = $o->abstractexception_ptr_id;
                                DB::connection('pgsql_original')->table('att_payloadexception')->where('item_id', (string)$oid)->delete();
                                DB::connection('pgsql_original')->table('att_leave')->where('abstractexception_ptr_id', $oid)->delete();
                                DB::connection('pgsql_original')->table('workflow_abstractexception')->where('id', $oid)->delete();
                            }
                            $this->insertarRegistroHistorico($row);
                        });
                        $this->info("   ✅ Reemplazo ejecutado con éxito.");
                        continue;
                    }
                }

                // Inserción normal transaccional
                DB::connection('pgsql_original')->transaction(function() use ($row) {
                    $this->insertarRegistroHistorico($row);
                });
            }
        }
    }

    /**
     * Inserta los registros rescatando los datos del respaldo.
     * MEJORA: Maneja casos de IDs huérfanos para evitar el error de Unique Violation.
     */
    private function insertarRegistroHistorico($row)
    {
        if ($this->option('dry-run')) return;

        $id = $row->abstractexception_ptr_id;

        // Obtenemos el registro padre del respaldo
        $parent = DB::connection('pgsql_biotime')->table('workflow_abstractexception')->where('id', $id)->first();
        
        if ($parent) {
            // CORRECCIÓN QUIRÚRGICA: Si el folio ya existe en la tabla padre pero no en la hija,
            // eliminamos el huérfano para re-insertarlo correctamente.
            $existePadre = DB::connection('pgsql_original')->table('workflow_abstractexception')->where('id', $id)->exists();
            
            if ($existePadre) {
                // Limpiamos rastro previo para evitar 'Unique violation'
                DB::connection('pgsql_original')->table('att_payloadexception')->where('item_id', (string)$id)->delete();
                DB::connection('pgsql_original')->table('att_leave')->where('abstractexception_ptr_id', $id)->delete();
                DB::connection('pgsql_original')->table('workflow_abstractexception')->where('id', $id)->delete();
            }

            // Insertamos en la Original respetando el estatus
            DB::connection('pgsql_original')->table('workflow_abstractexception')->insert((array)$parent);
            DB::connection('pgsql_original')->table('att_leave')->insert((array)$row);
            
            // Borramos caché para que BioTime reconozca el nuevo permiso
            DB::connection('pgsql_original')->table('att_payloadexception')->where('item_id', (string)$id)->delete();
        }
    }
}