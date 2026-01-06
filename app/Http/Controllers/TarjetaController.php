<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Repositories\TarjetaRepository;
use Carbon\Carbon;
use Inertia\Inertia;
use Exception;

class TarjetaController extends Controller
{
    protected $repository;

    public function __construct(TarjetaRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * --- MÓDULO INDIVIDUAL ---
     * Muestra la vista "Mi Tarjeta".
     */
    public function indexIndividual()
    {
        $user = Auth::user();
        
        // 1. CORRECCIÓN DEFINITIVA:
        // 'biotime_id' en Laravel es la Foreign Key que apunta al 'id' (PK) de personnel_employee.
        $pkBiotime = $user->biotime_id; 

        $empleadoData = null;

        // 2. Buscamos al empleado en el repositorio coincidiendo por su ID interno (PK)
        if ($pkBiotime) {
            try {
                $allEmployees = $this->repository->getAllEmployees();
                
                foreach ($allEmployees as $emp) {
                    // Comparamos el ID interno de la base de datos de Biotime con la FK del usuario
                    if (strval($emp->id) === strval($pkBiotime)) {
                        $empleadoData = $emp;
                        break;
                    }
                }
            } catch (Exception $e) {
                error_log("Error buscando empleado Biotime por ID: " . $e->getMessage());
            }
        }

        // 3. Fallback si no se encuentra
        if (!$empleadoData) {
            $empleadoData = [
                'id' => $pkBiotime ?? 'N/A', // ID Interno
                'emp_code' => 'N/A', // Código de empleado visual
                'first_name' => $user->name,
                'last_name' => '', 
                'department_name' => 'No vinculado (Verificar biotime_id)',
                'job_title' => ''
            ];
        }

        return Inertia::render('MiTarjeta', [
            'empleado' => $empleadoData
        ]);
    }

    /**
     * --- DESCARGA DE PDF ---
     */
    public function downloadPdf(Request $request)
    {
        $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer',
        ]);

        try {
            $user = Auth::user();
            // Usamos la FK para loguear o validar
            $empId = $user->biotime_id ?? 'N/A';
            
            return response()->json(['message' => 'Descarga iniciada para PK: ' . $empId]);

        } catch (Exception $e) {
            return response()->json(['error' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * --- API: OBTENER USUARIOS ---
     */
    public function getUsers()
    {
        try {
            $users = $this->repository->getAllEmployees();
            return response()->json(['users' => $users]);
        } catch (Exception $e) {
            error_log('ERROR en getUsers: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * --- API: OBTENER HORARIO ---
     */
    public function getSchedule(Request $request)
    {
        try {
            $request->validate([
                'emp_id' => 'required',
                'month' => 'required',
                'year' => 'required'
            ]);

            $empId = $request->emp_id;
            $month = $request->month;
            $year = $request->year;

            $startOfMonth = Carbon::createFromDate($year, $month, 1)->startOfMonth()->format('Y-m-d');
            $endOfMonth = Carbon::createFromDate($year, $month, 1)->endOfMonth()->format('Y-m-d');

            $registrosRaw = $this->repository->getAttendanceRecords($empId, $startOfMonth, $endOfMonth);
            $holidaysRaw = $this->repository->getHolidays($startOfMonth, $endOfMonth);

            if (empty($registrosRaw)) {
                return response()->json(['horario' => null, 'registros' => []]);
            }

            $registrosProcesados = $this->transformarRegistros($registrosRaw, $holidaysRaw, $startOfMonth, $endOfMonth);

            $horarioTexto = 'Sin horario';
            if (isset($registrosRaw[0]) && $registrosRaw[0]->in_time && $registrosRaw[0]->duration) {
                try {
                    $startTime = Carbon::createFromFormat('H:i:s', $registrosRaw[0]->in_time);
                    $endTime = (clone $startTime)->addMinutes($registrosRaw[0]->duration);
                    $horarioTexto = $startTime->format('H:i:s') . ' A ' . $endTime->format('H:i:s');
                } catch (Exception $e) {
                    $horarioTexto = $registrosRaw[0]->in_time;
                }
            }

            return response()->json([
                'horario' => $horarioTexto,
                'registros' => $registrosProcesados
            ]);

        } catch (Exception $e) {
            error_log('ERROR en getSchedule: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // --- LÓGICA PRIVADA ---

    private function transformarRegistros($registros, $holidays, $startDate, $endDate)
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        
        $retardosLevesPrevios = 0;
        $resultados = [];

        for ($date = $start; $date->lte($end); $date->addDay()) {
            $fechaActualStr = $date->format('Y-m-d');
            $registroDia = null;
            
            foreach ($registros as $reg) {
                if (Carbon::parse($reg->att_date)->format('Y-m-d') === $fechaActualStr) {
                    $registroDia = $reg;
                    break;
                }
            }

            $holidayDia = null;
            foreach ($holidays as $hol) {
                if (Carbon::parse($hol->start_date)->format('Y-m-d') === $fechaActualStr) {
                    $holidayDia = $hol;
                    break;
                }
            }

            $esFinDeSemana = $date->isWeekend();

            if (!$registroDia || $esFinDeSemana || ($holidayDia && isset($registroDia->enable_holiday) && $registroDia->enable_holiday === true)) {
                $resultados[] = [
                    'dia' => $fechaActualStr,
                    'checkin' => '',
                    'checkout' => '',
                    'calificacion' => 'DESC',
                    'observaciones' => $holidayDia ? $holidayDia->alias : ''
                ];
                continue;
            }

            $calificacion = $this->evaluarRetardo($registroDia, $retardosLevesPrevios);

            if ($calificacion === 'RL') $retardosLevesPrevios += 1;
            if ($retardosLevesPrevios >= 4) {
                $calificacion = 'RG';
                $retardosLevesPrevios = 0;
            }
            if ($calificacion === 'F' && !empty($registroDia->apply_reason)) {
                $calificacion = 'J';
            }

            $resultados[] = [
                'dia' => $fechaActualStr,
                'checkin' => $registroDia->clock_in ? Carbon::parse($registroDia->clock_in)->format('H:i:s') : '',
                'checkout' => $registroDia->clock_out ? Carbon::parse($registroDia->clock_out)->format('H:i:s') : '',
                'calificacion' => $calificacion,
                'observaciones' => $registroDia->apply_reason ?? ''
            ];
        }
        return $resultados;
    }

    private function evaluarRetardo($registro, $retardosLevesPrevios)
    {
        if (empty($registro->clock_in)) return 'F';

        $fechaCheckIn = Carbon::parse($registro->check_in)->format('Y-m-d');
        $horaEntradaEstandar = Carbon::parse($fechaCheckIn . ' ' . $registro->in_time);
        $horaRealEntrada = Carbon::parse($registro->clock_in);
        $diferenciaMinutos = $horaEntradaEstandar->diffInMinutes($horaRealEntrada, false);
        $tolerance = $registro->allow_late - 1;

        if ($diferenciaMinutos <= $tolerance) return 'OK';
        if ($diferenciaMinutos > $tolerance && $diferenciaMinutos <= 20) {
            return ($retardosLevesPrevios >= 4) ? 'RG' : 'RL';
        }
        if ($diferenciaMinutos > 20 && $diferenciaMinutos <= 31) return 'RG';

        return 'F';
    }
}