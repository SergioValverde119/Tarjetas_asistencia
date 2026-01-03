<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class ImportUsersFromCsv extends Command
{
    protected $signature = 'app:import-users {file}';
    protected $description = 'Importa usuarios desde CSV y verifica existencia en BioTime';

    public function handle()
    {
        $file = $this->argument('file');

        if (!file_exists($file)) {
            $this->error("❌ El archivo no existe: $file");
            return;
        }

        $this->info("📂 Leyendo archivo...");
        $handle = fopen($file, "r");
        
        // Descomenta si tu CSV tiene encabezados en la fila 1
        // fgetcsv($handle); 

        $creados = 0;
        $actualizados = 0;
        $errores = 0;
        
        // Contadores de coincidencia con BioTime
        $encontradosEnBioTime = 0;
        $noEncontradosEnBioTime = 0;

        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            // [0]=ID(emp_code), [1]=NOMBRE, [2]=RFC(username)
            if (count($data) < 3) continue;

            $empCode = trim($data[0]); 
            $name = trim(utf8_encode($data[1])); 
            $rfc = trim($data[2]); 

            if (empty($rfc) || empty($empCode)) {
                $this->warn("⚠️ Fila saltada (datos vacíos): " . json_encode($data));
                $errores++;
                continue;
            }

            // 1. VERIFICACIÓN EN BIOTIME
            // Consultamos si este ID existe en la base de datos real del reloj
            $existeEnBioTime = DB::connection('pgsql_biotime')
                ->table('personnel_employee')
                ->where('emp_code', $empCode) // BioTime usa bigint, el string se casta automático
                ->exists();

            if ($existeEnBioTime) {
                $encontradosEnBioTime++;
                $tag = "[✅ BIO-OK]";
            } else {
                $noEncontradosEnBioTime++;
                $tag = "[⚠️ NO-BIO]"; // Alerta visual
            }

            // 2. CREACIÓN / ACTUALIZACIÓN LOCAL
            try {
                $user = User::where('username', $rfc)->first();

                if ($user) {
                    $user->update([
                        'name' => $name,
                        'emp_code' => $empCode,
                    ]);
                    $actualizados++;
                    $this->line("🔄 Actualizado: $name ($rfc) - ID: $empCode $tag");
                } else {
                    $user = User::create([
                        'name' => $name,
                        'username' => $rfc,
                        'emp_code' => $empCode,
                        'email' => null,
                        'password' => Hash::make($empCode), // Pass = ID Empleado
                    ]);
                    $creados++;
                    $this->info("➕ Creado: $name ($rfc) - ID: $empCode $tag");
                }

            } catch (\Exception $e) {
                $this->error("❌ Error con $rfc: " . $e->getMessage());
                $errores++;
            }
        }

        fclose($handle);

        // 3. REPORTE FINAL
        $this->info("\n================================");
        $this->info("   RESUMEN DE IMPORTACIÓN");
        $this->info("================================");
        $this->info("👥 Usuarios Procesados:");
        $this->info("   - Nuevos Creados: $creados");
        $this->info("   - Actualizados:   $actualizados");
        $this->info("   - Errores:        $errores");
        $this->info("\n🔍 Auditoría BioTime:");
        $this->info("   - ✅ Coinciden con BioTime: $encontradosEnBioTime");
        
        if ($noEncontradosEnBioTime > 0) {
            $this->error("   - ⚠️ NO existen en BioTime: $noEncontradosEnBioTime");
            $this->line("     (Estos usuarios no verán información en su Kárdex)");
        } else {
            $this->info("   - 🌟 ¡Perfecto! Todos existen en BioTime.");
        }
        $this->info("================================");
    }
}