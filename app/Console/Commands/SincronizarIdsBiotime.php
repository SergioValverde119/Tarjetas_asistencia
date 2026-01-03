<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SincronizarIdsBiotime extends Command
{
    /**
     * El nombre para llamar al comando en la terminal.
     * Ejemplo: php artisan app:sincronizar-ids-biotime
     */
    protected $signature = 'app:sincronizar-ids-biotime';

    protected $description = 'Sincroniza el ID interno de BioTime con los usuarios locales buscando por Código o Nombre';

    public function handle()
    {
        $users = User::all();
        $total = $users->count();

        $this->info("Iniciando sincronización para $total usuarios...");
        
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $encontradosPorCodigo = 0;
        $encontradosPorNombre = 0;
        $noEncontrados = 0;

        foreach ($users as $user) {
            $bioEmployee = null;

            // --- ESTRATEGIA 1: BUSCAR POR CÓDIGO DE EMPLEADO ---
            if ($user->emp_code) {
                $bioEmployee = DB::connection('pgsql_biotime')
                    ->table('personnel_employee')
                    ->where('emp_code', $user->emp_code)
                    ->first();
                
                if ($bioEmployee) {
                    $encontradosPorCodigo++;
                }
            }

            // --- ESTRATEGIA 2: BUSCAR POR NOMBRE (SI FALLÓ EL CÓDIGO) ---
            if (!$bioEmployee) {
                // BioTime tiene first_name y last_name separados.
                // Usamos ILIKE para que no importen mayúsculas/minúsculas.
                // Concatenamos con un espacio en medio.
                
                $nombreBuscado = trim($user->name);

                $bioEmployee = DB::connection('pgsql_biotime')
                    ->table('personnel_employee')
                    // Truco de Postgres: Concatenar columnas para comparar con el nombre completo
                    ->whereRaw("TRIM(CONCAT(first_name, ' ', last_name)) ILIKE ?", [$nombreBuscado])
                    ->first();

                if ($bioEmployee) {
                    $encontradosPorNombre++;
                    // Opcional: Si lo encontramos por nombre, actualizamos el emp_code local
                    // para que la próxima vez sea más rápido.
                    if ($user->emp_code != $bioEmployee->emp_code) {
                        $user->emp_code = $bioEmployee->emp_code;
                    }
                }
            }

            // --- GUARDAR RESULTADO ---
            if ($bioEmployee) {
                $user->biotime_id = $bioEmployee->id;
                $user->save();
            } else {
                $noEncontrados++;
                // Descomenta esto si quieres ver quiénes fallan:
                // $this->error("\nNo encontrado: {$user->name} (Code: {$user->emp_code})");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("======================================");
        $this->info(" RESULTADOS DE SINCRONIZACIÓN");
        $this->info("======================================");
        $this->info("✅ Encontrados por Código: $encontradosPorCodigo");
        $this->info("✅ Encontrados por Nombre: $encontradosPorNombre (Rescatados)");
        $this->error("❌ No encontrados:        $noEncontrados");
        $this->info("======================================");
    }
}
