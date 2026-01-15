<?php

namespace App\Services;

use App\Repositories\TarjetaRepository;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * --- EL CEREBRO DEL SISTEMA (SERVICIO) ---
 * Imagina que este archivo es el "Gerente" de la oficina.
 * Él no va al almacén a cargar cajas (eso lo hace el Repositorio),
 * ni atiende a los clientes en la puerta (eso lo hace el Controlador).
 * * Su trabajo es PENSAR, CALCULAR y TOMAR DECISIONES basándose en las reglas
 * de la empresa (si llegaste tarde, si tienes permiso, etc).
 */
class TarjetaService
{
    /**
     * @var TarjetaRepository
     * Aquí guardamos la referencia a nuestro "Almacenista" (Repositorio).
     * Él es quien tiene las llaves de la base de datos de BioTime.
     */
    protected $repository;

    /**
     * CONSTRUCTOR: El inicio de todo.
     * Cuando Laravel crea a este Gerente, le asigna su Almacenista de confianza.
     * Sin el repositorio, el servicio no tendría datos para trabajar.
     */
    public function __construct(TarjetaRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * --- TAREA 1: TRAER LA LISTA DE TODOS ---
     * Esta función es simple: Le pide al almacenista la lista completa de empleados.
     * Sirve para llenar las listas desplegables en la pantalla del administrador.
     */
    public function obtenerTodosLosEmpleados()
    {
        try {
            // "Oye Repositorio, dame todos los empleados que tengas".
            return $this->repository->getAllEmployees();
        } catch (Exception $e) {
            // Si algo falla (se fue la luz, no hay conexión), anotamos el error en la bitácora (Log)
            // y devolvemos una lista vacía para que el sistema no explote.
            Log::error("Error en TarjetaService@obtenerTodosLosEmpleados: " . $e->getMessage());
            return []; 
        }
    }

    /**
     * --- TAREA 2: BUSCAR A ALGUIEN EN ESPECÍFICO ---
     * Recibe un ID (ej. el número 54) y busca quién es esa persona en BioTime.
     */
    public function buscarEmpleadoPorBiotimeId($biotimeId)
    {
        // Si no me das un ID, no puedo buscar nada.
        if (!$biotimeId) return null;

        try {
            // Traemos a todos y buscamos uno por uno hasta encontrar al correcto.
            $allEmployees = $this->repository->getAllEmployees();
            foreach ($allEmployees as $emp) {
                // Comparamos: ¿El ID de este empleado es igual al que busco?
                // Usamos strval para asegurarnos de comparar texto con texto.
                if (strval($emp->id) === strval($biotimeId)) {
                    return $emp; // ¡Lo encontré! Aquí está.
                }
            }
        } catch (Exception $e) {
            Log::error("Error en TarjetaService@buscarEmpleado: " . $e->getMessage());
        }
        return null; // Si revisé todo y no estaba, devuelvo "nulo" (nadie).
    }

    /**
     * --- TAREA 3: EL REPORTE ANUAL (EL TABLERO DE COLORES) ---
     * Esta función es la que decide qué meses se pintan de ROJO (con faltas).
     * Revisa todo el año, mes por mes, para ver cómo se comportó el empleado.
     */
    public function calcularResumenFaltasAnual($empleadoId, $year)
    {
        $resumenFaltas = []; // Aquí iremos guardando los "taches".
        
        try {
            // Paso 1: Definimos las fechas. Desde el primer segundo del año hasta el último.
            // Usamos H:i:s para que sea exacto (2025-01-01 00:00:00 hasta 2025-12-31 23:59:59).
            $startOfYear = Carbon::createFromDate($year, 1, 1)->startOfYear()->format('Y-m-d H:i:s');
            $endOfYear = Carbon::createFromDate($year, 12, 31)->endOfYear()->format('Y-m-d H:i:s');

            // Paso 2: Le pedimos al Almacenista TODOS los datos del año de un solo golpe.
            // Es más rápido traer todo en un viaje que hacer 365 viajes a la base de datos.
            $registrosYear = $this->repository->getAttendanceRecords($empleadoId, $startOfYear, $endOfYear);
            $holidaysYear = $this->repository->getHolidays($startOfYear, $endOfYear);
            
            // Paso 3: Traemos los permisos (vacaciones, incapacidades).
            // Primero preguntamos si el almacenista sabe buscar permisos (method_exists) por seguridad.
            $permisosYear = [];
            if (method_exists($this->repository, 'getPermissions')) {
                $permisosYear = $this->repository->getPermissions($empleadoId, $startOfYear, $endOfYear);
            }

            // Paso 4: Revisamos MES por MES (del 1 al 12).
            for ($m = 1; $m <= 12; $m++) {
                // REGLA DE ORO: No podemos juzgar el futuro.
                // Si estamos en Marzo (mes 3), no calculamos Abril (mes 4). Paramos aquí.
                if ($year == now()->year && $m > now()->month) break;

                // Definimos cuándo empieza y termina ESTE mes que estamos revisando.
                $inicioMes = Carbon::createFromDate($year, $m, 1)->startOfMonth()->format('Y-m-d');
                $finMes = Carbon::createFromDate($year, $m, 1)->endOfMonth()->format('Y-m-d');

                // Filtramos en memoria: De la montaña de registros del año, sacamos solo los de este mes.
                $registrosMes = array_filter($registrosYear, fn($r) => Carbon::parse($r->att_date)->month == $m);

                // LLAMADA AL MOTOR DE REGLAS:
                // Aquí mandamos los datos crudos a la función "transformarRegistros" que es la que piensa.
                // Ella nos devuelve la lista ya calificada (con Faltas, Retardos, etc).
                $procesado = $this->transformarRegistros($registrosMes, $holidaysYear, $permisosYear, $inicioMes, $finMes);

                // Paso 5: Buscamos si hubo alguna 'F' (Falta) en el resultado.
                $diasFalta = [];
                foreach ($procesado as $dia) {
                    if ($dia['calificacion'] === 'F') {
                        $diasFalta[] = Carbon::parse($dia['dia'])->day; // Guardamos el día (ej. día 5, día 20).
                    }
                }

                // Si hubo faltas, las anotamos en el resumen del mes.
                if (!empty($diasFalta)) {
                    $resumenFaltas[$m] = $diasFalta;
                }
            }
        } catch (Exception $e) {
            Log::error("Error calculando resumen anual: " . $e->getMessage());
        }

        // Devolvemos la lista de meses "reprobados".
        return $resumenFaltas;
    }

    /**
     * --- TAREA 4: EL REPORTE DETALLADO (PARA EL PDF) ---
     * Esta función hace lo mismo que la anterior, pero solo para UN mes específico.
     * Devuelve el detalle fino: hora de entrada, hora de salida, observaciones, etc.
     */
    public function obtenerDatosPorMes($empleadoId, $month, $year)
    {
        try {
            // Definimos el inicio y fin del mes solicitado con precisión de segundos.
            $startOfMonth = Carbon::createFromDate($year, $month, 1)->startOfMonth()->format('Y-m-d H:i:s');
            $endOfMonth = Carbon::createFromDate($year, $month, 1)->endOfMonth()->format('Y-m-d H:i:s');

            // Pedimos los datos al repositorio.
            $registrosRaw = $this->repository->getAttendanceRecords($empleadoId, $startOfMonth, $endOfMonth);
            $holidaysRaw = $this->repository->getHolidays($startOfMonth, $endOfMonth);
            
            $permisosRaw = method_exists($this->repository, 'getPermissions') 
                ? $this->repository->getPermissions($empleadoId, $startOfMonth, $endOfMonth) 
                : [];

            // Si no hay nada de nada, devolvemos vacío.
            if (empty($registrosRaw) && empty($permisosRaw)) {
                return ['horario' => null, 'registros' => []];
            }

            // Procesamos los datos con el Motor de Reglas.
            $registrosProcesados = $this->transformarRegistros($registrosRaw, $holidaysRaw, $permisosRaw, $startOfMonth, $endOfMonth);

            // Intentamos adivinar el horario del empleado viendo su primera checada.
            // Esto es solo informativo para ponerlo en el encabezado del PDF.
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

            // Entregamos el paquete completo listo para imprimir.
            return [
                'horario' => $horarioTexto,
                'registros' => $registrosProcesados
            ];

        } catch (Exception $e) {
            Log::error("Error en TarjetaService@obtenerDatosPorMes: " . $e->getMessage());
            throw $e;
        }
    }

    // --- ZONA PRIVADA: EL MOTOR DE REGLAS ---
    // Estas funciones son internas, solo las usa el mismo servicio.

    /**
     * Esta es la función MÁS IMPORTANTE. 
     * Transforma una lista de checadas crudas en una boleta de calificaciones diaria.
     */
    private function transformarRegistros($registros, $holidays, $permisos, $startDate, $endDate)
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        
        $retardosLevesPrevios = 0; // Contador de "retarditos" (4 hacen 1 grave).
        $resultados = [];

        // Recorremos el calendario día por día (ej. 1, 2, 3... 30).
        for ($date = $start; $date->lte($end); $date->addDay()) {
            $fechaActualStr = $date->format('Y-m-d');
            
            // 1. ¿Vino a trabajar? Buscamos si hay checada con la fecha de hoy.
            $registroDia = null;
            foreach ($registros as $reg) {
                if (str_starts_with($reg->att_date, $fechaActualStr)) {
                    $registroDia = $reg;
                    break;
                }
            }

            // 2. ¿Tiene permiso? (Aquí está la lógica inteligente de rangos).
            // Revisamos si el día de hoy cae DENTRO de las fechas de algún permiso.
            $permisoDia = null;
            foreach ($permisos as $p) {
                $pStart = Carbon::parse($p->start_date);
                $pEnd = Carbon::parse($p->end_date);
                if ($date->between($pStart, $pEnd)) {
                    $permisoDia = $p; // ¡Sí tiene permiso hoy!
                    break;
                }
            }

            // 3. ¿Es día festivo?
            $holidayDia = null;
            foreach ($holidays as $hol) {
                if (str_starts_with($hol->start_date, $fechaActualStr)) {
                    $holidayDia = $hol;
                    break;
                }
            }

            // --- AQUI APLICAMOS LAS REGLAS DE JERARQUÍA ---

            // REGLA 1 (La más fuerte): Si tiene PERMISO, se justifica automáticamente.
            // No importa si llegó tarde o no vino, el permiso manda.
            if ($permisoDia) {
                $resultados[] = $this->crearFila($fechaActualStr, $registroDia, 'J', $permisoDia->reason ?? 'Permiso');
                continue; // Pasamos al siguiente día.
            }

            // REGLA 2: Si es Fin de Semana, es descanso.
            if ($date->isWeekend()) {
                $resultados[] = $this->crearFila($fechaActualStr, null, 'DESC', '');
                continue;
            }

            // REGLA 3: Si es Festivo oficial, es descanso.
            if (!$registroDia || ($holidayDia && isset($registroDia->enable_holiday) && $registroDia->enable_holiday === true)) {
                $resultados[] = $this->crearFila($fechaActualStr, null, 'DESC', $holidayDia ? $holidayDia->alias : '');
                continue;
            }

            // REGLA 4: Si no hay registro, no es festivo y no tiene permiso... es FALTA.
            if (!$registroDia) {
                $resultados[] = $this->crearFila($fechaActualStr, null, 'F', '');
                continue;
            }

            // REGLA 5: Si sí vino, evaluamos qué tan tarde llegó.
            $calificacion = $this->evaluarRetardo($registroDia, $retardosLevesPrevios);

            // Manejo de retardos acumulados:
            if ($calificacion === 'RL') $retardosLevesPrevios++; // Sumamos un retardo leve.
            if ($retardosLevesPrevios >= 4) {
                $calificacion = 'RG'; // 4 leves se convierten en 1 grave.
                $retardosLevesPrevios = 0; // Reiniciamos la cuenta.
            }
            
            // Última oportunidad: Si el registro en BioTime tiene una nota de justificación, perdonamos la falta.
            if ($calificacion === 'F' && !empty($registroDia->apply_reason)) {
                $calificacion = 'J';
            }

            // Guardamos el resultado final del día.
            $resultados[] = $this->crearFila($fechaActualStr, $registroDia, $calificacion, $registroDia->apply_reason ?? '');
        }
        return $resultados;
    }

    /**
     * Ayuda visual para crear el array bonito que recibe el PDF.
     */
    private function crearFila($fecha, $registro, $calificacion, $obs) {
        return [
            'dia' => $fecha,
            'checkin' => ($registro && $registro->clock_in) ? Carbon::parse($registro->clock_in)->format('H:i:s') : '',
            'checkout' => ($registro && $registro->clock_out) ? Carbon::parse($registro->clock_out)->format('H:i:s') : '',
            'calificacion' => $calificacion,
            'observaciones' => $obs
        ];
    }

    /**
     * La calculadora de minutos. Decide si es OK, Retardo Leve, Grave o Falta.
     */
    private function evaluarRetardo($registro, $retardosLevesPrevios)
    {
        // Si marcó salida pero no entrada, es Falta.
        if (empty($registro->clock_in)) return 'F';

        // Calculamos la hora exacta que debía entrar y la que llegó.
        $fechaCheckIn = Carbon::parse($registro->check_in)->format('Y-m-d');
        $horaEntradaEstandar = Carbon::parse($fechaCheckIn . ' ' . $registro->in_time);
        $horaRealEntrada = Carbon::parse($registro->clock_in);
        
        // Diferencia en minutos.
        $diferenciaMinutos = $horaEntradaEstandar->diffInMinutes($horaRealEntrada, false);
        $tolerance = $registro->allow_late - 1; // Un minutito de gracia extra.

        // Semáforo de retardos:
        if ($diferenciaMinutos <= $tolerance) return 'OK'; // Verde: Llegó a tiempo.
        
        // Amarillo: Retardo Leve (entre tolerancia y 20 min).
        // (Aquí hay una lógica recursiva curiosa que depende del historial).
        if ($diferenciaMinutos > $tolerance && $diferenciaMinutos <= 20) return ($retardosLevesPrevios >= 4) ? 'RG' : 'RL';
        
        // Naranja: Retardo Grave (entre 20 y 31 min).
        if ($diferenciaMinutos > 20 && $diferenciaMinutos <= 31) return 'RG';
        
        // Rojo: Llegó tardísimo (más de 31 min). Cuenta como Falta.
        return 'F';
    }
}