<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Repositories\TarjetaRepository;
use Carbon\Carbon;
use Exception;

class TarjetaController extends Controller
{
    protected $repository;

    public function __construct(TarjetaRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Endpoint: /api/internal/users
     */
    public function getUsers()
    {
        error_log('----------------------------------------------------');
        error_log('DEBUG: Inicio de petición getUsers (/api/internal/users)');

        try {
            // Intentamos obtener los usuarios
            $users = $this->repository->getAllEmployees();
            
            error_log('DEBUG: Consulta a BD ejecutada con éxito.');
            error_log('DEBUG: Cantidad de empleados encontrados: ' . count($users));

            if (count($users) > 0) {
                // Imprimimos el primer usuario para verificar estructura
                error_log('DEBUG: Ejemplo del primer registro: ' . json_encode($users[0]));
            } else {
                error_log('DEBUG: ¡OJO! La consulta no devolvió ningún registro (Array vacío).');
            }

            return response()->json(['users' => $users]);

        } catch (Exception $e) {
            error_log('ERROR CRÍTICO en getUsers:');
            error_log('Mensaje: ' . $e->getMessage());
            return response()->json(['error' => 'Error al obtener usuarios: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Endpoint: /api/internal/schedules
     */
    public function getSchedule(Request $request)
    {
        error_log('----------------------------------------------------');
        error_log('DEBUG: Inicio de petición getSchedule (/api/internal/schedules)');
        
        try {
            $request->validate([
                'emp_id' => 'required',
                'month' => 'required',
                'year' => 'required'
            ]);

            $empId = $request->emp_id;
            $month = $request->month;
            $year = $request->year;

            error_log("DEBUG: Parámetros recibidos -> EmpID: $empId, Mes: $month, Año: $year");

            // 1. Obtener fechas de inicio y fin de mes
            $startOfMonth = Carbon::createFromDate($year, $month, 1)->startOfMonth()->format('Y-m-d');
            $endOfMonth = Carbon::createFromDate($year, $month, 1)->endOfMonth()->format('Y-m-d');

            // 2. Consultar BD
            error_log("DEBUG: Consultando asistencias entre $startOfMonth y $endOfMonth");
            $registrosRaw = $this->repository->getAttendanceRecords($empId, $startOfMonth, $endOfMonth);
            $holidaysRaw = $this->repository->getHolidays($startOfMonth, $endOfMonth);

            error_log('DEBUG: Registros de asistencia encontrados: ' . count($registrosRaw));
            error_log('DEBUG: Días festivos encontrados: ' . count($holidaysRaw));

            if (empty($registrosRaw)) {
                error_log('DEBUG: No hay registros para procesar, devolviendo vacío.');
                return response()->json(['horario' => null, 'registros' => []]);
            }

            // 3. Procesar lógica de negocio (retardos, faltas, etc.)
            $registrosProcesados = $this->transformarRegistros($registrosRaw, $holidaysRaw, $startOfMonth, $endOfMonth);

            // 4. Calcular texto del horario
            $horarioTexto = 'Sin horario';
            
            // CORRECCIÓN APLICADA AQUÍ: Usamos 'H:i:s' en lugar de 'H:mm:ss'
            if (isset($registrosRaw[0]) && $registrosRaw[0]->in_time && $registrosRaw[0]->duration) {
                try {
                    $startTime = Carbon::createFromFormat('H:i:s', $registrosRaw[0]->in_time);
                    $endTime = (clone $startTime)->addMinutes($registrosRaw[0]->duration);
                    $horarioTexto = $startTime->format('H:i:s') . ' A ' . $endTime->format('H:i:s');
                } catch (Exception $e) {
                    error_log("ADVERTENCIA: No se pudo formatear el horario. Error: " . $e->getMessage());
                    $horarioTexto = $registrosRaw[0]->in_time; // Fallback
                }
            }
            
            error_log("DEBUG: Horario calculado: $horarioTexto");

            return response()->json([
                'horario' => $horarioTexto,
                'registros' => $registrosProcesados
            ]);

        } catch (Exception $e) {
            error_log('ERROR CRÍTICO en getSchedule:');
            error_log('Mensaje: ' . $e->getMessage());
            error_log('Trace: ' . $e->getTraceAsString());
            return response()->json(['error' => 'Error al obtener horario: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Lógica principal de iteración de días.
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

            // Lógica de "Descanso" (DESC)
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

            // Contar Retardos Leves
            if ($calificacion === 'RL') {
                $retardosLevesPrevios += 1;
            }

            // Regla: 4 Retardos Leves = 1 Retardo Grave
            if ($retardosLevesPrevios >= 4) {
                $calificacion = 'RG';
                $retardosLevesPrevios = 0;
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
            // Verifica regla de acumulación dentro de la evaluación
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