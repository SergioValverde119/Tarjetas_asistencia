<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Repositories\TarjetaRepository;
use App\Models\HistorialDescarga; // Modelo para guardar quién descargó el PDF
use Carbon\Carbon;
use Inertia\Inertia;
use Exception;

class TarjetaController extends Controller
{
    protected $repository;

    // Inyección de Dependencias: Laravel nos "regala" una instancia del Repositorio aquí.
    public function __construct(TarjetaRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * --- MÓDULO INDIVIDUAL (VISTA PRINCIPAL) ---
     * Esta función carga la pantalla cuando el empleado entra a ver sus tarjetas.
     */
    public function indexIndividual()
    {
        $user = Auth::user();
        
        // 1. VINCULACIÓN: Obtenemos el ID de enlace (biotime_id) del usuario logueado.
        $pkBiotime = $user->biotime_id;
        $year = 2025; // Año fijo para este módulo

        $empleadoData = null;
        $resumenFaltas = []; // Aquí guardaremos qué meses tienen "taches" (Faltas)

        // 2. BUSQUEDA DE EMPLEADO:
        // Si el usuario tiene un ID vinculado, buscamos sus datos reales en BioTime via el Repositorio.
        if ($pkBiotime) {
            try {
                $allEmployees = $this->repository->getAllEmployees();
                
                foreach ($allEmployees as $emp) {
                    // Comparamos el ID de la base de datos (strval para evitar errores de tipo)
                    if (strval($emp->id) === strval($pkBiotime)) {
                        $empleadoData = $emp;
                        break; // ¡Encontrado! Dejamos de buscar.
                    }
                }
            } catch (Exception $e) {
                error_log("Error buscando empleado Biotime: " . $e->getMessage());
            }
        }

        // 3. DATOS DEL EMPLEADO (Fallback):
        // Si no lo encontramos, creamos un objeto "falso" para que la vista no se rompa.
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
            // --- CÁLCULO DE ESTATUS (Faltas del año) ---
            // Solo entramos aquí si el empleado existe.
            try {
                // Definimos el rango del año completo (Enero 1 a Dic 31)
                $startOfYear = Carbon::createFromDate($year, 1, 1)->startOfYear()->format('Y-m-d');
                $endOfYear = Carbon::createFromDate($year, 12, 31)->endOfYear()->format('Y-m-d');

                // PEDIDO AL REPOSITORIO: "Traeme todas las checadas y festivos del año"
                // Hacemos una sola consulta grande en lugar de 12 pequeñas (Mejora rendimiento).
                $registrosYear = $this->repository->getAttendanceRecords($empleadoData->id, $startOfYear, $endOfYear);
                $holidaysYear = $this->repository->getHolidays($startOfYear, $endOfYear);

                // CICLO MES POR MES (1 al 12)
                for ($m = 1; $m <= 12; $m++) {
                    // REGLA DE TIEMPO: Si el mes es futuro (ej. estamos en Marzo y vamos en el ciclo 4-Abril), paramos.
                    if ($year == now()->year && $m > now()->month) break;

                    $inicioMes = Carbon::createFromDate($year, $m, 1)->startOfMonth()->format('Y-m-d');
                    $finMes = Carbon::createFromDate($year, $m, 1)->endOfMonth()->format('Y-m-d');

                    // FILTRADO EN MEMORIA:
                    // De todos los registros del año, separamos solo los de ESTE mes ($m).
                    $registrosMes = array_filter($registrosYear, fn($r) => Carbon::parse($r->att_date)->month == $m);
                    $holidaysMes = array_filter($holidaysYear, fn($h) => Carbon::parse($h->start_date)->month == $m);

                    // LÓGICA DE NEGOCIO:
                    // Enviamos los datos crudos a procesar para obtener calificaciones (OK, RL, RG, F).
                    $procesado = $this->transformarRegistros($registrosMes, $holidaysMes, $inicioMes, $finMes);

                    // CONTEO DE FALTAS:
                    // Revisamos el resultado procesado. Si hay una 'F', guardamos el día.
                    $diasFalta = [];
                    foreach ($procesado as $dia) {
                        if ($dia['calificacion'] === 'F') {
                            $diasFalta[] = Carbon::parse($dia['dia'])->day;
                        }
                    }

                    // Si hubo faltas en este mes, las guardamos en el resumen para bloquear el botón en la vista.
                    if (!empty($diasFalta)) {
                        $resumenFaltas[$m] = $diasFalta;
                    }
                }
            } catch (Exception $e) {
                error_log("Error calculando resumen: " . $e->getMessage());
            }
        }

        // 4. HISTORIAL: Buscamos qué meses ya descargó este usuario anteriormente.
        $descargasPrevias = HistorialDescarga::where('user_id', $user->id)
            ->where('year', $year)
            ->pluck('month')
            ->toArray();

        // 5. RESPUESTA: Enviamos todo a la vista de Vue (Inertia).
        return Inertia::render('MiTarjeta', [
            'empleado' => $empleadoData,
            'descargasPrevias' => $descargasPrevias,
            'resumenFaltas' => $resumenFaltas
        ]);
    }

    /**
     * --- REGISTRO DE DESCARGA ---
     * Se llama cuando el usuario confirma la descarga del PDF.
     */
    public function downloadPdf(Request $request)
    {
        $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer',
        ]);

        try {
            $user = Auth::user();
            
            // Guardamos o actualizamos el registro en la base de datos local (MySQL)
            HistorialDescarga::updateOrInsert(
                [
                    'user_id' => $user->id,
                    'month' => $request->month,
                    'year' => $request->year
                ],
                [
                    'downloaded_at' => now(),
                    'ip_address' => $request->ip()
                ]
            );
            
            return response()->json(['status' => 'success', 'message' => 'Descarga registrada']);

        } catch (Exception $e) {
            return response()->json(['error' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * --- API USUARIOS ---
     * Usada probablemente para selectores o administración.
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
     * --- API GENERACIÓN PDF ---
     * Esta función devuelve los datos DETALLADOS para armar el PDF de un mes específico.
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

            // Calculamos fechas límite del mes solicitado
            $startOfMonth = Carbon::createFromDate($year, $month, 1)->startOfMonth()->format('Y-m-d');
            $endOfMonth = Carbon::createFromDate($year, $month, 1)->endOfMonth()->format('Y-m-d');

            // Pedimos datos crudos al Repositorio
            $registrosRaw = $this->repository->getAttendanceRecords($empId, $startOfMonth, $endOfMonth);
            $holidaysRaw = $this->repository->getHolidays($startOfMonth, $endOfMonth);

            if (empty($registrosRaw)) {
                return response()->json(['horario' => null, 'registros' => []]);
            }

            // Procesamos la lógica de negocio (Faltas/Retardos)
            $registrosProcesados = $this->transformarRegistros($registrosRaw, $holidaysRaw, $startOfMonth, $endOfMonth);

            // Intentamos adivinar el horario base del empleado tomando el primer registro disponible
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

    // --- LÓGICA PRIVADA (EL MOTOR DE REGLAS) ---

    /**
     * Transforma una lista de checadas crudas en una lista de días con calificación.
     * Rellena huecos de días faltantes.
     */
    private function transformarRegistros($registros, $holidays, $startDate, $endDate)
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        
        $retardosLevesPrevios = 0; // Contador acumulativo de retardos leves
        $resultados = [];

        // RECORREMOS CADA DÍA DEL RANGO (ej. del 1 al 30 de Abril)
        for ($date = $start; $date->lte($end); $date->addDay()) {
            $fechaActualStr = $date->format('Y-m-d');
            $registroDia = null;
            
            // 1. ¿Hay checada este día?
            foreach ($registros as $reg) {
                if (Carbon::parse($reg->att_date)->format('Y-m-d') === $fechaActualStr) {
                    $registroDia = $reg;
                    break;
                }
            }

            // 2. ¿Es día festivo?
            $holidayDia = null;
            foreach ($holidays as $hol) {
                if (Carbon::parse($hol->start_date)->format('Y-m-d') === $fechaActualStr) {
                    $holidayDia = $hol;
                    break;
                }
            }

            $esFinDeSemana = $date->isWeekend();

            // CASO: DÍA NO LABORABLE O DESCANSO
            // Si no hay registro Y (es fin de semana O es festivo habilitado), marcamos DESC.
            if (!$registroDia || $esFinDeSemana || ($holidayDia && isset($registroDia->enable_holiday) && $registroDia->enable_holiday === true)) {
                $resultados[] = [
                    'dia' => $fechaActualStr,
                    'checkin' => '',
                    'checkout' => '',
                    'calificacion' => 'DESC', // Descanso
                    'observaciones' => $holidayDia ? $holidayDia->alias : ''
                ];
                continue; // Pasamos al siguiente día
            }

            // CASO: DÍA LABORABLE CON ASISTENCIA (O FALTA DE ELLA)
            // Evaluamos minutos de retardo.
            $calificacion = $this->evaluarRetardo($registroDia, $retardosLevesPrevios);

            // Regla de acumulación: 4 Retardos Leves (RL) = 1 Retardo Grave (RG)
            if ($calificacion === 'RL') $retardosLevesPrevios += 1;
            if ($retardosLevesPrevios >= 4) {
                $calificacion = 'RG';
                $retardosLevesPrevios = 0; // Reiniciamos contador
            }
            
            // Regla de Justificación: Si es Falta pero tiene "apply_reason" en BD, pasa a 'J'.
            if ($calificacion === 'F' && !empty($registroDia->apply_reason)) {
                $calificacion = 'J';
            }

            // Agregamos el resultado final del día
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

    /**
     * Calcula la calificación basada estrictamente en la hora de entrada vs checada.
     */
    private function evaluarRetardo($registro, $retardosLevesPrevios)
    {
        // Regla de Oro: Si no hay checada de entrada = Falta.
        if (empty($registro->clock_in)) return 'F';

        // Cálculos de tiempo
        $fechaCheckIn = Carbon::parse($registro->check_in)->format('Y-m-d');
        $horaEntradaEstandar = Carbon::parse($fechaCheckIn . ' ' . $registro->in_time); // Hora que DEBÍA llegar
        $horaRealEntrada = Carbon::parse($registro->clock_in); // Hora que LLEGÓ
        
        // Diferencia en minutos (Positivo = Tarde, Negativo = Temprano)
        $diferenciaMinutos = $horaEntradaEstandar->diffInMinutes($horaRealEntrada, false);
        
        // Tolerancia configurada en BioTime (ej. 10 minutos)
        // Se resta 1 para ajuste estricto (ej. min 10 es OK, min 11 es retardo)
        $tolerance = $registro->allow_late - 1;

        // --- REGLAS DURAS ---
        if ($diferenciaMinutos <= $tolerance) return 'OK'; // Llegó a tiempo
        
        // Entre tolerancia y 20 min = Retardo Leve
        if ($diferenciaMinutos > $tolerance && $diferenciaMinutos <= 20) {
            // (Nota: aquí hay una lógica recursiva curiosa que depende del acumulado previo)
            return ($retardosLevesPrevios >= 4) ? 'RG' : 'RL';
        }
        
        // Entre 21 y 31 min = Retardo Grave directo
        if ($diferenciaMinutos > 20 && $diferenciaMinutos <= 31) return 'RG';

        // Más de 31 min = Falta
        return 'F';
    }

    // --- PANEL DE ADMINISTRADOR ---
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