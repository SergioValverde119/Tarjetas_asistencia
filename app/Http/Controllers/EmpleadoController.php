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
        // 1. Buscar datos generales del empleado (con Depto y Puesto)
        $empleado = DB::connection('pgsql_biotime')
            ->table('personnel_employee as e')
            ->leftJoin('personnel_department as d', 'e.department_id', '=', 'd.id')
            ->leftJoin('personnel_position as p', 'e.position_id', '=', 'p.id')
            ->select(
                'e.id', 'e.emp_code', 'e.first_name', 'e.last_name', 
                'e.hire_date', 'e.birthday', 'e.mobile', 'e.ssn', 'e.photo',
                'd.dept_name', 'p.position_name'
            )
            ->where('e.id', $id)
            ->first();

        if (!$empleado) {
            abort(404, 'Empleado no encontrado');
        }

        // 2. Calcular Kárdex del MES ACTUAL para las estadísticas
        $hoy = Carbon::now();
        $inicioMes = $hoy->copy()->startOfMonth();
        $finMes = $hoy->copy()->endOfMonth();
        
        // Reutilizamos tu repositorio para no reescribir la lógica de faltas/retardos
        // Truco: Pasamos un array con un solo empleado
        $payloadData = $this->kardexRepo->getPayloadData([$empleado->id], $inicioMes, $finMes);
        $permisos = $this->kardexRepo->getPermisos([$empleado->id], $inicioMes, $finMes);

        // Procesamos (esto nos devuelve el array con totales)
        $kardexProcesado = $this->kardexRepo->procesarKardex(
            [$empleado], // Array de 1 elemento
            $payloadData,
            $permisos,
            $hoy->month,
            $hoy->year,
            1, // Día 1
            $hoy->daysInMonth // Último día
        );

        // Extraemos el único registro procesado
        $stats = $kardexProcesado[0];

        return Inertia::render('Empleado/Show', [
            'empleado' => $empleado,
            'stats' => $stats,
            'fechaActual' => $hoy->isoFormat('MMMM YYYY'), // Ej: "Noviembre 2025"
        ]);
    }
}
