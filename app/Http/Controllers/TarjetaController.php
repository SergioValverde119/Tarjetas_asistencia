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
     * --- NUEVO: MÓDULO INDIVIDUAL ---
     * Muestra la vista "Mi Tarjeta" con los datos del empleado logueado.
     * Ruta: /MiTarjeta
     */
    public function indexIndividual()
    {
        $user = Auth::user();

        // Preparamos los datos del empleado para mostrarlos en la cabecera de la vista
        // Si tienes una tabla separada de empleados, aquí harías una consulta extra:
        // $empleadoDB = $this->repository->findEmployeeById($user->employee_id);
        
        $empleadoData = [
            'id' => $user->employee_id ?? $user->id,
            'first_name' => $user->name,
            'last_name' => $user->last_name ?? '',
            'department_name' => $user->department ?? 'General',
            'job_title' => $user->position ?? 'Colaborador',
        ];

        return Inertia::render('MiTarjeta', [
            'empleado' => $empleadoData
        ]);
    }

    /**
     * --- NUEVO: DESCARGA DE PDF ---
     * Genera el reporte de asistencia para un mes específico.
     * Ruta: /MiTarjeta/descargar
     */
    public function downloadPdf(Request $request)
    {
        $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer',
        ]);

        try {
            $user = Auth::user();
            $empId = $user->employee_id ?? $user->id;
            $month = $request->month;
            $year = $request->year;

            // 1. Obtener rango de fechas
            $startOfMonth = Carbon::createFromDate($year, $month, 1)->startOfMonth()->format('Y-m-d');
            $endOfMonth = Carbon::createFromDate($year, $month, 1)->endOfMonth()->format('Y-m-d');

            // 2. Obtener datos (aquí reutilizamos la lógica de asistencia si vas a armar el PDF con datos reales)
            // $registros = $this->repository->getAttendanceRecords($empId, $startOfMonth, $endOfMonth);
            
            // --- SIMULACIÓN DE PDF ---
            // Nota: Aquí debes integrar tu librería de PDF favorita (DomPDF o Snappy)
            // Ejemplo real: 
            // $pdf = PDF::loadView('pdf.tarjeta', compact('registros', 'user'));
            // return $pdf->download("Tarjeta_{$empId}_{$month}_{$year}.pdf");
            
            $content = "Reporte de Asistencia (Simulado)\n\nEmpleado: {$user->name}\nID: {$empId}\nPeriodo: {$month}/{$year}\n\n(Aquí se mostraría la tabla generada por DomPDF o Snappy)";
            
            return response($content)
                ->header('Content-Type', 'application/pdf') // Cambia a text/plain si estás probando sin librería PDF
                ->header('Content-Disposition', 'attachment; filename="Tarjeta_' . $empId . '_' . $month . '_' . $year . '.pdf"');

        } catch (Exception $e) {
            return response()->json(['error' => 'Error al generar PDF: ' . $e->getMessage()], 500);
        }
    }

    /**
     * --- API: OBTENER USUARIOS ---
     * Usado por: Tarjetas Generales (Admin)
     * Endpoint: /api/internal/users
     */
    public function getUsers()
    {
        // error_log('DEBUG: Inicio de petición getUsers'); // Descomentar para debug

        try {
            $users = $this->repository->getAllEmployees();
            return response()->json(['users' => $users]);
        } catch (Exception $e) {
            error_log('ERROR en getUsers: ' . $e->getMessage());
            return response()->json(['error' => 'Error al obtener usuarios: ' . $e->getMessage()], 500);
        }
    }

    /**
     * --- API: OBTENER HORARIO Y ASISTENCIA ---
     * Usado por: Tarjetas Generales y MiTarjeta (si necesitaras ver detalle en el futuro)
     * Endpoint: /api/internal/schedules
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

            // 1. Definir rango de fechas
            $startOfMonth = Carbon::createFromDate($year, $month, 1)->startOfMonth()->format('Y-m-d');
            $endOfMonth = Carbon::createFromDate($year, $month, 1)->endOfMonth()->format('Y-m-d');

            // 2. Consultar BD
            $registrosRaw = $this->repository->getAttendanceRecords($empId, $startOfMonth, $endOfMonth);
            $holidaysRaw = $this->repository->getHolidays($startOfMonth, $endOfMonth);

            if (empty($registrosRaw)) {
                return response()->json(['horario' => null, 'registros' => []]);
            }

            // 3. Procesar lógica de negocio (retardos, faltas, descansos)
            $registrosProcesados = $this->transformarRegistros($registrosRaw, $holidaysRaw, $startOfMonth, $endOfMonth);

            // 4. Calcular texto del horario (basado en el primer registro encontrado)
            $horarioTexto = 'Sin horario';
            
            if (isset($registrosRaw[0]) && $registrosRaw[0]->in_time && $registrosRaw[0]->duration) {
                try {
                    $startTime = Carbon::createFromFormat('H:i:s', $registrosRaw[0]->in_time);
                    $endTime = (clone $startTime)->addMinutes($registrosRaw[0]->duration);
                    $horarioTexto = $startTime->format('H:i:s') . ' A ' . $endTime->format('H:i:s');
                } catch (Exception $e) {
                    $horarioTexto = $registrosRaw[0]->in_time; // Fallback si falla el formato
                }
            }

            return response()->json([
                'horario' => $horarioTexto,
                'registros' => $registrosProcesados
            ]);

        } catch (Exception $e) {
            error_log('ERROR en getSchedule: ' . $e->getMessage());
            return response()->json(['error' => 'Error al obtener horario: ' . $e->getMessage()], 500);
        }
    }

    /**
     * --- PRIVADO: LÓGICA DE PROCESAMIENTO ---
     * Itera día por día para determinar el estado de asistencia.
     */
    private function transformarRegistros($registros, $holidays, $startDate, $endDate)
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        
        $retardosLevesPrevios = 0;
        $resultados = [];

        // Iterar día por día del mes
        for ($date = $start; $date->lte($end); $date->addDay()) {
            $fechaActualStr = $date->format('Y-m-d');

            // Buscar registro existente para este día
            $registroDia = null;
            foreach ($registros as $reg) {
                if (Carbon::parse($reg->att_date)->format('Y-m-d') === $fechaActualStr) {
                    $registroDia = $reg;
                    break;
                }
            }

            // Buscar si es festivo
            $holidayDia = null;
            foreach ($holidays as $hol) {
                if (Carbon::parse($hol->start_date)->format('Y-m-d') === $fechaActualStr) {
                    $holidayDia = $hol;
                    break;
                }
            }

            $esFinDeSemana = $date->isWeekend();

            // Lógica de "Descanso" (DESC) o Festivo
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

            // Evaluar Retardo
            $calificacion = $this->evaluarRetardo($registroDia, $retardosLevesPrevios);

            // Contar Retardos Leves para acumulación
            if ($calificacion === 'RL') {
                $retardosLevesPrevios += 1;
            }

            // Regla: 4 Retardos Leves = 1 Retardo Grave
            if ($retardosLevesPrevios >= 4) {
                $calificacion = 'RG';
                $retardosLevesPrevios = 0; // Reiniciar contador
            }

            // Regla: Falta con Justificación = J
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

    /**
     * --- PRIVADO: REGLAS DE TIEMPO ---
     * Evalúa la calificación del registro (OK, RL, RG, F).
     */
    private function evaluarRetardo($registro, $retardosLevesPrevios)
    {
        if (empty($registro->clock_in)) {
            return 'F';
        }

        // Construir hora estándar de entrada
        $fechaCheckIn = Carbon::parse($registro->check_in)->format('Y-m-d');
        $horaEntradaEstandar = Carbon::parse($fechaCheckIn . ' ' . $registro->in_time);
        
        $horaRealEntrada = Carbon::parse($registro->clock_in);

        // Diferencia en minutos (negativo = temprano/a tiempo, positivo = tarde)
        $diferenciaMinutos = $horaEntradaEstandar->diffInMinutes($horaRealEntrada, false);

        // Tolerancia
        $tolerance = $registro->allow_late - 1;

        if ($diferenciaMinutos <= $tolerance) {
            return 'OK';
        }

        if ($diferenciaMinutos > $tolerance && $diferenciaMinutos <= 20) {
            // Verifica regla de acumulación dentro de la evaluación (opcional, ya se maneja fuera)
            if ($retardosLevesPrevios >= 4) {
                return 'RG';
            }
            return 'RL';
        }

        if ($diferenciaMinutos > 20 && $diferenciaMinutos <= 31) {
            return 'RG';
        }

        return 'F';
    }
}