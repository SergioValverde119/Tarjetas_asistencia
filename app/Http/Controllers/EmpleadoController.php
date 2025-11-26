<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Repositories\KardexRepository;

class EmpleadoController extends Controller
{
    protected $kardexRepo;

    public function __construct(KardexRepository $kardexRepo)
    {
        $this->kardexRepo = $kardexRepo;
    }

    public function show($id)
    {
        error_log(">>> [EMPLEADO] 1. Iniciando show() para ID: " . $id);

        try {
            // 1. Buscar datos generales del empleado
            error_log(">>> [EMPLEADO] 2. Consultando datos generales en BioTime...");
            
            $empleado = DB::connection('pgsql_biotime')
                ->table('personnel_employee as e')
                ->leftJoin('personnel_department as d', 'e.department_id', '=', 'd.id')
                ->leftJoin('personnel_position as p', 'e.position_id', '=', 'p.id')
                ->select(
                    'e.id', 'e.emp_code', 'e.first_name', 'e.last_name', 
                    'e.hire_date', 'e.birthday', 'e.mobile', 'e.ssn', 'e.photo',
                    'd.dept_name', 'p.position_name',
                    // --- ¡ESTA ES LA LÍNEA QUE FALTABA! ---
                    // Agregamos la misma subconsulta que en el repositorio para traer la nómina
                    DB::raw("(
                        SELECT STRING_AGG(pa.area_name, ', ')
                        FROM public.personnel_employee_area pea
                        JOIN public.personnel_area pa ON pea.area_id = pa.id
                        WHERE pea.employee_id = e.id
                        AND pa.area_name != 'SEDUVI' 
                    ) as nomina")
                )
                ->where('e.id', $id)
                ->first();

            if (!$empleado) {
                error_log("!!! [EMPLEADO ERROR] Empleado no encontrado en BD.");
                abort(404, 'Empleado no encontrado');
            }
            error_log(">>> [EMPLEADO] 3. Empleado encontrado: " . $empleado->first_name);

            // 2. Calcular Kárdex del MES ACTUAL
            $hoy = Carbon::now();
            $inicioMes = $hoy->copy()->startOfMonth();
            $finMes = $hoy->copy()->endOfMonth();
            
            error_log(">>> [EMPLEADO] 4. Rango de fechas: " . $inicioMes->toDateString() . " a " . $finMes->toDateString());

            // Llamadas al repositorio
            error_log(">>> [EMPLEADO] 5. Obteniendo Payload y Permisos...");
            $payloadData = $this->kardexRepo->getPayloadData([$empleado->id], $inicioMes, $finMes);
            $permisos = $this->kardexRepo->getPermisos([$empleado->id], $inicioMes, $finMes);
            
            error_log(">>> [EMPLEADO] 6. Procesando Kárdex (procesarKardex)...");
            
            // IMPORTANTE: procesarKardex espera un array o colección de empleados
            // Le pasamos un array con un solo objeto
            $kardexProcesado = $this->kardexRepo->procesarKardex(
                [$empleado], 
                $payloadData,
                $permisos,
                $hoy->month,
                $hoy->year,
                1, // Día 1
                $hoy->daysInMonth // Último día
            );

            if (empty($kardexProcesado)) {
                throw new \Exception("procesarKardex devolvió un array vacío.");
            }

            // Extraemos el único registro procesado
            $stats = $kardexProcesado[0];
            error_log(">>> [EMPLEADO] 7. Estadísticas calculadas. Faltas: " . $stats['total_faltas']);

            error_log(">>> [EMPLEADO] 8. Renderizando vista 'Empleado/Show'...");
            
            return Inertia::render('Empleado/Show', [
                'empleado' => $empleado,
                'stats' => $stats,
                'fechaActual' => $hoy->isoFormat('MMMM YYYY'), // Ej: "Noviembre 2025"
            ]);

        } catch (\Throwable $e) {
            error_log('!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!');
            error_log('>>> [EMPLEADO ERROR FATAL]: ' . $e->getMessage());
            error_log('>>> Archivo: ' . $e->getFile() . ' línea ' . $e->getLine());
            error_log('!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!');
            throw $e;
        }
    }
}