<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\TarjetaService;
use Carbon\Carbon;
use App\Models\User; // Importamos el modelo User para filtrar

class CheckFaltasCommand extends Command
{
    /**
     * El nombre y la firma del comando.
     * Ejemplo: php artisan biotime:check-faltas 02 2025
     */
    protected $signature = 'biotime:check-faltas {month : Mes a revisar (1-12)} {year? : Año (Opcional)}';

    /**
     * Descripción del comando.
     */
    protected $description = 'Busca empleados con Faltas (F) o Retardos Graves (RG) usando las reglas del Servicio (Solo usuarios registrados)';

    protected $tarjetaService;

    /**
     * Inyección de Dependencias.
     * Laravel nos da el servicio listo para usar.
     */
    public function __construct(TarjetaService $tarjetaService)
    {
        parent::__construct();
        $this->tarjetaService = $tarjetaService;
    }

    public function handle()
    {
        $month = (int) $this->argument('month');
        $year = (int) ($this->argument('year') ?? date('Y'));

        if ($month < 1 || $month > 12) {
            $this->error('El mes debe ser un número entre 1 y 12.');
            return;
        }

        $this->info("🔄 Calculando incidencias para el periodo: $month/$year...");
        $this->info("   (Solo se revisarán empleados que tengan cuenta de usuario en el sistema)");

        // 1. Obtener TODOS los empleados activos desde el Servicio
        $allEmpleados = $this->tarjetaService->obtenerTodosLosEmpleados();

        // 2. FILTRAR: Solo procesar empleados que tengan un usuario vinculado en Laravel
        // Obtenemos los IDs de BioTime que están en uso en la tabla users
        $idsConCuenta = User::whereNotNull('biotime_id')
            ->pluck('biotime_id')
            ->map(fn($id) => (string)$id) // Convertimos a string para asegurar comparación exacta
            ->toArray();

        // Filtramos el array original de empleados
        $empleadosFiltrados = array_filter($allEmpleados, function($emp) use ($idsConCuenta) {
            return in_array((string)$emp->id, $idsConCuenta);
        });

        $total = count($empleadosFiltrados);
        
        if ($total === 0) {
            $this->error("No se encontraron empleados vinculados con cuentas de usuario.");
            return;
        }

        $this->info("   -> Procesando $total usuarios vinculados.");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $reporte = [];

        foreach ($empleadosFiltrados as $emp) {
            // 3. Usar el Servicio para calcular la tarjeta de ese mes
            // Esto aplica TODAS las reglas: festivos, permisos por rango, fines de semana, retardos.
            $datosTarjeta = $this->tarjetaService->obtenerDatosPorMes($emp->id, $month, $year);
            
            $faltas = 0;
            $retardosGraves = 0;
            $diasDetalle = [];

            // 4. Analizar los resultados del servicio
            foreach ($datosTarjeta['registros'] as $dia) {
                if ($dia['calificacion'] === 'F') {
                    $faltas++;
                    $diasDetalle[] = substr($dia['dia'], 8, 2) . '(F)'; // Ej: "15(F)"
                }
                if ($dia['calificacion'] === 'RG') {
                    $retardosGraves++;
                    $diasDetalle[] = substr($dia['dia'], 8, 2) . '(RG)'; // Ej: "12(RG)"
                }
            }

            // 5. Si tiene incidencias, lo agregamos al reporte
            if ($faltas > 0 || $retardosGraves > 0) {
                $reporte[] = [
                    'nomina' => $emp->emp_code,
                    'nombre' => $emp->last_name .' ' .$emp->first_name,
                    'faltas' => $faltas,
                    'rg' => $retardosGraves,
                    'detalle' => implode(', ', $diasDetalle)
                ];
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // 6. Mostrar resultados
        if (count($reporte) > 0) {
            $this->error("🚨 SE ENCONTRARON " . count($reporte) . " USUARIOS CON INCIDENCIAS:");
            $this->table(
                ['Nómina', 'Nombre', 'Faltas', 'R.Graves', 'Días'],
                $reporte
            );
        } else {
            $this->info("✅ ¡Felicidades! Ningún usuario vinculado tiene faltas ni retardos graves en este mes.");
        }
    }
}