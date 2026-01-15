<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Repositories\TarjetaRepository;
use App\Models\HistorialDescarga;
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
     */
    public function indexIndividual()
    {
        $user = Auth::user();
        
        // 1. Vinculación
        $pkBiotime = $user->biotime_id;
        $year = 2025; 

        $empleadoData = null;
        $resumenFaltas = []; 

        // 2. Búsqueda de Empleado
        if ($pkBiotime) {
            try {
                $allEmployees = $this->repository->getAllEmployees();
                foreach ($allEmployees as $emp) {
                    if (strval($emp->id) === strval($pkBiotime)) {
                        $empleadoData = $emp;
                        break;
                    }
                }
            } catch (Exception $e) {
                error_log("Error buscando empleado: " . $e->getMessage());
            }
        }

        // 3. Fallback
        if (!$empleadoData) {
            $empleadoData = [
                'id' => $pkBiotime ?? 'N/A',
                'emp_code' => 'N/A',
                'first_name' => $user->name,
                'last_name' => '',
                'department_name' => 'No vinculado',
                'job_title' => ''
            ];
        } else {
            // --- CÁLCULO DE ESTATUS ---
            try {
                $startOfYear = Carbon::createFromDate($year, 1, 1)->startOfYear()->format('Y-m-d');
                $endOfYear = Carbon::createFromDate($year, 12, 31)->endOfYear()->format('Y-m-d');

                // Consultas al repositorio
                $registrosYear = $this->repository->getAttendanceRecords($empleadoData->id, $startOfYear, $endOfYear);
                $holidaysYear = $this->repository->getHolidays($startOfYear, $endOfYear);
                
                // --- NUEVO: Traer Permisos (Excepciones) ---
                // DEBUG: Verificar si el método existe y qué devuelve
                if (method_exists($this->repository, 'getPermissions')) {
                    $permisosYear = $this->repository->getPermissions($empleadoData->id, $startOfYear, $endOfYear);
                    error_log("DEBUG: Permisos encontrados para Empleado ID {$empleadoData->id}: " . count($permisosYear));
                    if (count($permisosYear) > 0) {
                        error_log("DEBUG: Primer permiso (Muestra): " . json_encode($permisosYear[0]));
                    }
                } else {
                    error_log("CRITICAL: El método 'getPermissions' NO EXISTE en TarjetaRepository. Revisa el archivo del repositorio.");
                    $permisosYear = [];
                }

                for ($m = 1; $m <= 12; $m++) {
                    // LÓGICA ORIGINAL: Si el mes es futuro, paramos. (Muestra el mes actual en curso)
                    if ($year == now()->year && $m > now()->month) break;

                    $inicioMes = Carbon::createFromDate($year, $m, 1)->startOfMonth()->format('Y-m-d');
                    $finMes = Carbon::createFromDate($year, $m, 1)->endOfMonth()->format('Y-m-d');

                    $registrosMes = array_filter($registrosYear, fn($r) => Carbon::parse($r->att_date)->month == $m);
                    $holidaysMes = array_filter($holidaysYear, fn($h) => Carbon::parse($h->start_date)->month == $m);

                    // --- PASAMOS LOS PERMISOS A LA FUNCIÓN ---
                    $procesado = $this->transformarRegistros($registrosMes, $holidaysMes, $permisosYear, $inicioMes, $finMes);

                    $diasFalta = [];
                    foreach ($procesado as $dia) {
                        if ($dia['calificacion'] === 'F') {
                            $diasFalta[] = Carbon::parse($dia['dia'])->day;
                        }
                    }

                    if (!empty($diasFalta)) {
                        $resumenFaltas[$m] = $diasFalta;
                    }
                }
            } catch (Exception $e) {
                error_log("Error calculando resumen: " . $e->getMessage());
            }
        }

        $descargasPrevias = HistorialDescarga::where('user_id', $user->id)
            ->where('year', $year)
            ->pluck('month')
            ->toArray();

        return Inertia::render('MiTarjeta', [
            'empleado' => $empleadoData,
            'descargasPrevias' => $descargasPrevias,
            'resumenFaltas' => $resumenFaltas
        ]);
    }

    /**
     * --- DESCARGA PDF ---
     */
    public function downloadPdf(Request $request)
    {
        $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer',
        ]);

        try {
            $user = Auth::user();
            HistorialDescarga::updateOrInsert(
                ['user_id' => $user->id, 'month' => $request->month, 'year' => $request->year],
                ['downloaded_at' => now(), 'ip_address' => $request->ip()]
            );
            return response()->json(['status' => 'success', 'message' => 'Descarga registrada']);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function getUsers()
    {
        try {
            return response()->json(['users' => $this->repository->getAllEmployees()]);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * --- API SCHEDULE ---
     */
    public function getSchedule(Request $request)
    {
        try {
            $request->validate(['emp_id' => 'required', 'month' => 'required', 'year' => 'required']);

            $empId = $request->emp_id;
            $month = $request->month;
            $year = $request->year;

            $startOfMonth = Carbon::createFromDate($year, $month, 1)->startOfMonth()->format('Y-m-d');
            $endOfMonth = Carbon::createFromDate($year, $month, 1)->endOfMonth()->format('Y-m-d');

            $registrosRaw = $this->repository->getAttendanceRecords($empId, $startOfMonth, $endOfMonth);
            $holidaysRaw = $this->repository->getHolidays($startOfMonth, $endOfMonth);
            
            // --- NUEVO: Traer Permisos y validar si existe método ---
            $permisosRaw = [];
            if (method_exists($this->repository, 'getPermissions')) {
                $permisosRaw = $this->repository->getPermissions($empId, $startOfMonth, $endOfMonth);
            } else {
                error_log("CRITICAL: getPermissions no existe en el repositorio (getSchedule)");
            }

            if (empty($registrosRaw) && empty($permisosRaw)) {
                return response()->json(['horario' => null, 'registros' => []]);
            }

            // --- PASAR PERMISOS ---
            $registrosProcesados = $this->transformarRegistros($registrosRaw, $holidaysRaw, $permisosRaw, $startOfMonth, $endOfMonth);

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

            return response()->json(['horario' => $horarioTexto, 'registros' => $registrosProcesados]);

        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // --- LÓGICA PRIVADA ---

    /**
     * Transforma registros. AHORA RECIBE $permisos.
     */
    private function transformarRegistros($registros, $holidays, $permisos, $startDate, $endDate)
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        
        $retardosLevesPrevios = 0;
        $resultados = [];

        for ($date = $start; $date->lte($end); $date->addDay()) {
            $fechaActualStr = $date->format('Y-m-d');
            
            // 1. Buscar Checada
            $registroDia = null;
            foreach ($registros as $reg) {
                // Comparamos fecha (quitamos hora si viene)
                $fechaReg = substr($reg->att_date, 0, 10);
                if ($fechaReg === $fechaActualStr) {
                    $registroDia = $reg;
                    break;
                }
            }

            // 2. NUEVO: Buscar Permiso por Rango (SOLUCIÓN A TU PROBLEMA)
            $permisoDia = null;
            foreach ($permisos as $p) {
                $pStart = Carbon::parse($p->start_date);
                $pEnd = Carbon::parse($p->end_date);
                
                // DEBUG: Ver qué está comparando si tenemos dudas
                // error_log("DEBUG: Comparando día $fechaActualStr con permiso {$p->start_date} - {$p->end_date}");

                // Si la fecha actual está DENTRO del rango del permiso
                if ($date->between($pStart, $pEnd)) {
                    $permisoDia = $p;
                    error_log("DEBUG: MATCH! Permiso encontrado para el día $fechaActualStr. Razón: " . ($p->reason ?? 'Sin razón'));
                    break;
                }
            }

            // 3. Buscar Festivo
            $holidayDia = null;
            foreach ($holidays as $hol) {
                // Comparamos fecha (quitamos hora si viene)
                $fechaHol = substr($hol->start_date, 0, 10);
                if ($fechaHol === $fechaActualStr) {
                    $holidayDia = $hol;
                    break;
                }
            }

            // PRIORIDAD A: Si hay permiso, es Justificado (J) directo.
            if ($permisoDia) {
                $resultados[] = [
                    'dia' => $fechaActualStr,
                    'checkin' => $registroDia ? substr($registroDia->clock_in, 11, 5) : '',
                    'checkout' => $registroDia ? substr($registroDia->clock_out, 11, 5) : '',
                    'calificacion' => 'J',
                    'observaciones' => $permisoDia->reason ?? 'Permiso'
                ];
                continue;
            }

            $esFinDeSemana = $date->isWeekend();

            // CASO: DESCANSOS
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

            // CASO: EVALUAR RETARDOS
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

    public function indexLogs(Request $request) {
        $query = \App\Models\HistorialDescarga::with('user')->orderBy('downloaded_at', 'desc');

        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('user', function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('biotime_id', 'like', "%{$search}%");
            });
        }

        return Inertia::render('LogsDescargas', [
            'logs' => $query->paginate(15)->withQueryString(),
            'filters' => $request->only(['search'])
        ]);
    }
}